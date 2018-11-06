<?php
/**********
** Author: github.com/switch998
** Description: This script scrapes your proboards forum and inserts the posts to IPB. It will convert (most) BBCode to HTML for IPS 4.x
** -- After running this, you must recount your users' posts from the admin CP and rebuild the search index
**
** Notice: This script is not completely developed or tested and was tested only IPS Converters installed.
**
** On: November 4th, 2018
** Pre-Alpha
************/

namespace IPS\Application;

//change these to your forum's init file
include(__DIR__.'/forum/init.php');

//Define variables

$GLOBALS['forum_subdomain'] = ''; //Enter your forum's subdomain, do not include the full domain.

$GLOBALS['cookies'] = ''; //Add your tapatalk session cookies here eg "Cookie: __cfduid=xyz; session_id=xyz

//define user IDs to translate to the new site, tapatalk_userid => ipb_userid
//You can delete these two arrays if the user IDs have not changed, but make sure to comment out the checks in the getPost function
$users_tapatalk_to_ipb = array(1 => 16, 2 => 1, 3 => 17, 4 => 20, 5 => 18, 8 => 15, 10 => 19, 20 => 13, 21 => 12);

//define forum names to translate to the new site, tapatalk_forumid => ipb_forumid
$forums_tapatalk_to_ipb = array(3 => 7, 16 => 10, 14 => 15, 20 => 16, 22 => 12, 21 => 13, 23 => 11, 15 => 17, 5 => 20, 1 => 19, 10 => 19, 9 => 21, 12 => 29, 7 => 28, 17 => 28);

//newest thread id in your forum.
$last_thread = '5';

class Proboards extends \IPS\Application {
	public function showBBcodes($text) {
		// BBcode array
		$find = array(
			'~\[b\](.*?)\[/b\]~s',
			'~\[i\](.*?)\[/i\]~s',
			'~\[u\](.*?)\[/u\]~s',
			'~\[s\](.*?)\[\/s\]~s',
			'~\[quote.*?author="(.*?)"(?:.*?)\](.*?)\[\/quote\]~s',
			'~\[size=(.*?)\](.*?)\[/size\]~s',
			'~\[color=(.*?)\](.*?)\[/color\]~s',
			'~\[url\=(.*?)\](.*?)\[\/url\]~s',
			'~\[url\](.*?)\[\/url\]~s',
			'~\[h1\](.*?)\[\/h1\]~s',
			'~\[h2\](.*?)\[\/h2\]~s',
			'~\[h3\](.*?)\[\/h3\]~s',
			'~\[h4\](.*?)\[\/h4\]~s',
			'~\[h5\](.*?)\[\/h5\]~s',
			'~\[h6\](.*?)\[\/h6\]~s',
			'~\[img\](.*?)\[\/img\]~s',
			 '~\[img.*?src="(.*?)"].*?~s',
			'~\[list=1\](.*?)\[\/list\]~s',
			'~\[list=a\](.*?)\[\/list\]~s',
			'~\[list\](.*?)\[\/list\]~s',
			'~\[\*\](.*)~s',
			'~\[code\](.*?)\[\/code\]~s',
			'~\[sub\](.*?)\[\/sub\]~s',
			'~\[sup\](.*?)\[\/sup\]~s',
			'~\[small\](.*?)\[\/small\]~s',
			 '~\[table\](.*?)\[\/table\]~s',
			 '~\[tr\](.*?)\[\/tr\]~s',
			 '~\[td\](.*?)\[\/td\]~s',
			 '~\[a.*?href="(.*?)"](.*?)\[\/a\]~s',
			 '~\[font.*?\](.*?)\[\/font\]~s',
			 '~\[attachment.*?id="(.*?)".*?\]~s',
			 '~\[quote.*?name="(.*?)"(?:.*?)\](.*?)\[\/quote\]~s',
			 '~\[quote\](.*?)\[\/quote\]~s'
		);
		// HTML tags to replace BBcode
		$replace = array(
			'<b>$1</b>',
			'<i>$1</i>',
			'<u>$1</u>',
			'<s>$1</s>',
			'<blockquote class="ipsQuote"><div class="ipsQuote_citation">$1 said:</div>
			$2</'.'blockquote>',
			'<span style="font-size:$1px;">$2</span>',
			'<span style="color:$1;">$2</span>',
			'<a href="$1">$1</a>',
			'<a href="$1">$1</a>',
			'<h1>$1</h1>',
			'<h2>$1</h2>',
			'<h3>$1</h3>',
			'<h4>$1</h4>',
			'<h5>$1</h5>',
			'<h6>$1</h6>',
			'<img src="$1" alt="" />',
			'<img src="$1" alt="" />',
			'<ol>$1</ol>',
			'<ol type="a">$1</ol>',
			'<ul>$1</ul>',
			'<li>$1</li>',
			'<code>$1</code>',
			'<sub>$1</sub>',
			'<sup>$1</sup>',
			'<small>$1</small>',
			'<table>$1</table>',
			'<tr>$1</tr>',
			'<td>$1</td>',
			'<a href="$1">$2</a>',
			'<span style="font-size:16pt;">$1</span>',
			'<a href="http://".$GLOBALS['forum_subdomain'].".proboards.com/attachment/download/$1">Legacy Attachment #$1</span>',
			'<blockquote class="ipsQuote"><div class="ipsQuote_citation">$1 said:</div>
			$2</'.'blockquote>',
			'<blockquote class="ipsQuote">$1</'.'blockquote>'
		);
		// Replacing the BBcodes with corresponding HTML tags
		$onepass =  preg_replace($find,$replace,nl2br($text));
		return preg_replace($find,$replace,$onepass);
	}

    public function getPost($this_iteration,$threadid) {
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://".$GLOBALS['forum_subdomain'].".proboards.com/mobiquo/mobiquo.cgi"); //your forum subdomain goes here, don't touch the URI
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "<methodCall><methodName>get_thread_by_unread</methodName><params><param><value><string>".(string)$threadid."</string></value></param><param><value><i4>".(string)$this_iteration."</i4></value></param></params></methodCall>");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		$headers = array();	
		$headers[] = "Host: ".$GLOBALS['forum_subdomain'].".proboards.com"; //define your host here
		$headers[] = "Accept: */*"; //dont touch
		$headers[] = "Fromapp: tapatalk"; //dont touch
		$headers[] = "Tt-App-Id: 2"; //dont touch
		$headers[] = "Mobiquoid: 2"; //dont touch
		$headers[] = "Accept-Language: en-us"; //dont touch
		$headers[] = "Content-Type: text/xml"; //dont touch
		$headers[] = "User-Agent: Mozilla/5.0 Firefox/3.5.6 Tapatalk/2082"; //dont touch except to update rev UA
		$headers[] = $GLOBALS['cookies'];  
		$headers[] = "Tt-Version: 2082"; //dont touch except in accordance with build string in user-agent header
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			exit('Failed at post number '.$this_iteration.' of thread id '.$postid.' with error:' . curl_error($ch));
		}
		curl_close ($ch);
		$xml = new \SimpleXMLElement($result);
		$rand = uniqid();
		$thispost[$rand] = array();
		$iteration[$rand] = 0;
	//	$libraryClass = $this->getLibrary();
		if(($xml->params->param->value->struct->member[0]->value->boolen) == 0 && (string)$xml->params->param->value->struct->member[0]->name == 'result_text'){
			$val = base64_decode($xml->params->param->value->struct->member[1]->value->base64 ?: $xml->params->param->value->struct->member[0]->value->base64);
			echo '<b>Received message</b>: '.$val.' <br> at post: '.$threadid.' and page: '.$this_iteration;
			if($val == 'Thread not found.'|| $val == 'You do not have access to this thread.'){
				echo 'Continuing Anyway.<br>';
				return;
			}else{
				echo "<- Quit because of previous message.";
				exit;
			}
		}
		foreach($xml->params->param->value->struct->member as $xmldecoded){
			if($xmldecoded->name == 'topic_title'){
				$thispost[$rand][0][post_title] = (string)$xmldecoded->value->base64;
			}
			if($xmldecoded->name == 'topic_id'){
				$thispost[$rand][0][topic_id] = (string)$xmldecoded->value->string;
			}
			if($xmldecoded->name == 'forum_name'){
				$thispost[$rand][0][forumname] = (string)$xmldecoded->value->base64;
			}
			if($xmldecoded->name == 'forum_id'){
				$thispost[$rand][0][forumid] = (string)$xmldecoded->value->string;
			}
			if($xmldecoded->name == 'total_post_num'){
				$thispost[$rand][0][totalpages] = (string)$xmldecoded->value->int;
			}
			if($xmldecoded->name == 'is_sticky'){
				$thispost[$rand][0][sticky] = (string)$xmldecoded->value->boolean;
			}
	
			if($xmldecoded->name == 'posts'){
				foreach($xmldecoded->value->array->data->value as $postdata){
					foreach($postdata->struct->member as $postmember){
						if($postmember->name == 'post_author_id'){
							$thispost[$rand][$iteration[$rand]][author] = (string)$postmember->value->string;
						}
						if($postmember->name == 'post_id'){
							$thispost[$rand][$iteration[$rand]][postnum] = (string)$postmember->value->string;
						}
						elseif($postmember->name == 'post_content'){
							$thispost[$rand][$iteration[$rand]][content] = (string)$postmember->value->base64;
						}
						elseif($postmember->name == 'post_title'){
							$thispost[$rand][$iteration[$rand]][title] = (string)$postmember->value->base64;
						}
						elseif($postmember->name == 'timestamp'){
							$thispost[$rand][$iteration[$rand]][timestamp] = (string)$postmember->value->string;
						}
						elseif($postmember->name == 'post_time'){
							$thispost[$rand][$iteration[$rand]][formatted_time] = (string)$postmember->value->{'dateTime.iso8601'};
						}
					}
					$iteration[$rand]++;
				}	
			}
		}
		$thispost[$rand][0]['lastpost'] = $iteration[$rand];
		//insert the posts to the database
		
		foreach($thispost[$rand] as $key => $post){
			if($GLOBALS['wasinserted'][$post[postnum]]){
				continue;
			}else{
				$GLOBALS['wasinserted'][$post[postnum]] = true;
				if(!empty($GLOBALS['forums_tapatalk_to_ipb'][$thispost[$rand][0][forumid]])){		
				if($key == 0 && $this_iteration == '0'){
							
					$topicdata =  array(
					'tid'               => $post['topic_id'],
					'title'             => base64_decode((string)$post[post_title]),
					'forum_id'          => $GLOBALS['forums_tapatalk_to_ipb'][(string)$post[forumid]],
					'state'             => 'open',
					'posts'             => (string)$post[totalpages],
					'starter_id'        => (int)$GLOBALS['users_tapatalk_to_ipb'][(string)$post[author]],
					'start_date'        => \IPS\DateTime::create()->setTimestamp( $post[timestamp] ),
					'last_poster_id'    =>  '', //$row['TOPIC_LAST_POSTER_ID'], see comment below
					'last_post'         =>  '', //\IPS\DateTime::create()->setTimestamp( $row['TOPIC_LAST_REPLY_TIME'] ), see comment below
					'last_poster_name'  =>  '', //, Unknown, tapatalk doesn't give us this info and we can not reliably extrapolate it unless we loop through all pages of a post and then add it to the DB. We will fix this outside of the script
					'views'             => '0',
					'topic_firstpost'   => $post[postnum],
					'approved'          => '1',
					'pinned'            => $post[sticky],
					'poll_state'        => '0');
					
				//	$this = new Proboards();
					$ipbid = $this->convertForumsTopic($topicdata);
					$GLOBALS['tapa_id'.$threadid]['ipid'] = $ipbid;
					$postadata =  array(
						'topic_id'         => $ipbid,
						'post_date'        => \IPS\DateTime::create()->setTimestamp( $post[timestamp] ),
						'new_topic'         => '1',
						'author_id'        => $GLOBALS['users_tapatalk_to_ipb'][(string)$post[author]] ?: 0,
						'post_key'          => $key+$this_iteration,
						'pid'	 => $post[postnum],
						'post'      	=> $this->showBBcodes(base64_decode((string)$post[content]))
						);
					$this->convertForumsPost($postadata);
												//$inserted_id = \IPS\Db::i()->insert( 'forums_topics', $topicdata );
					//$this->addLink( $post['topic_id'], $post['postnum'], 'forums_topics' );
					}else{
						$postadata =  array(
						'topic_id'         => $GLOBALS['tapa_id'.$threadid]['ipid'],
						'post_date'        => \IPS\DateTime::create()->setTimestamp( $post[timestamp] ),
						'new_topic'         => 0,
						'author_id'        => $GLOBALS['users_tapatalk_to_ipb'][(string)$post[author]] ?: 0,
						'post_key'          => $key+$this_iteration,
						'pid'	 => $post[postnum],
						'post'      	=> $this->showBBcodes(base64_decode((string)$post[content]))
						);
						$this->convertForumsPost($postadata);
					}
					if((int)$key == (int)$thispost[$rand][0][totalpages]-1){ //totalpages starts at one, key starts at zero
							$lastposterid = $GLOBALS['users_tapatalk_to_ipb'][(string)$post[author]] ?: 0;
							$lastpostdate = $post[timestamp];
							$last = \IPS\Member::load( $lastposterid );
							$lastpostername = $last->name;
							//last post update DB to reflect this author and date
							\IPS\Db::i()->query( "UPDATE forums_topics SET last_poster_id='".$lastposterid."', last_poster_name='".$lastpostername."',  last_post='".$lastpostdate."', last_real_post='".$lastpostdate."' WHERE tid='".$GLOBALS['tapa_id'.$threadid]['ipid']."'");
					}
				}
			}
		}
		if(($thispost[$rand][0]['lastpost'] + $thisiteration) < $thispost[$rand][0][totalpages]){
			return $this->getPost($this_iteration+10,$threadid);
		}
    }

   public function convertForumsTopic( $info=array() )
	{
		if ( !isset( $info['tid'] ) )
		{
		//	$this->software->app->log( 'topic_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['title'] ) )
		{
			$info['title'] = "Untitled Topic {$info['tid']}";
		//	$this->software->app->log( 'topic_missing_title', __METHOD__, \IPS\convert\App::LOG_WARNING );
		}
		
				
		if ( !isset( $info['state'] ) OR !in_array( $info['state'], array( 'open', 'closed' ) ) )
		{
			$info['state'] = 'open';
		}
		
		if ( !isset( $info['posts'] ) )
		{
			$info['posts'] = 0;
		}
		
		/*if ( isset( $info['starter_id'] ) )
		{
			try
			{
				$info['starter_id'] = $this->getLink( $info['starter_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['starter_id'] = 0;
			}
		}
		else
		{
			$info['starter_id'] = 0;
		}*/
		
		if ( isset( $info['start_date'] ) )
		{
			if ( $info['start_date'] instanceof \IPS\DateTime )
			{
				$info['start_date'] = $info['start_date']->getTimestamp();
			}
		}
		else
		{
			$info['start_date'] = time();
		}
		
		if ( isset( $info['last_poster_id'] ) )
		{
			try
			{
				$info['last_poster_id'] = $this->getLink( $info['last_poster_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['last_poster_id'] = $info['starter_id'];
			}
		}
		else
		{
			$info['last_poster_id'] = $info['starter_id'];
		}
		
		if ( isset( $info['last_post'] ) )
		{
			if ( $info['last_post'] instanceof \IPS\DateTime )
			{
				$info['last_post'] = $info['last_post']->getTimestamp();
			}
		}
		else
		{
			$info['last_post'] = $info['start_date'];
		}
		
		if ( !isset( $info['starter_name'] ) )
		{
			$starter = \IPS\Member::load( $info['starter_id'] );
			
			if ( $starter->member_id )
			{
				$info['starter_name'] = $starter->name;
			}
			else
			{
				$info['starter_name'] = NULL;
			}
		}
		
		if ( !isset( $info['last_poster_name'] ) )
		{
			$last = \IPS\Member::load( $info['last_poster_id'] );
			
			if ( $last->member_id )
			{
				$info['last_poster_name'] = $last->name;
			}
			else
			{
				/*
				 * We want to make sure that we're not setting NULL here, although allowed in some places it can
				 * cause issues with setting the last comment during the post-conversion topic deletion task.
				 */
				$info['last_poster_name'] = $info['starter_name'] ?: '';
			}
		}
		
		/* Polls - pass off to the core library. Unlike ratings and follows, we need to do this here as we need to know the Poll ID. */
		if ( isset( $info['poll_state'] ) AND is_array( $info['poll_state'] ) )
		{
			if ( $info['poll_state'] = $this->convertPoll( $info['poll_state']['poll_data'], $info['poll_state']['vote_data'] ) )
			{
				if ( !isset( $info['last_vote'] ) )
				{
					try
					{
						$lastVote = \IPS\Db::i()->select( 'vote_date', 'core_voters', array( "poll=?", $info['poll_state'] ) )->first();
					}
					catch( \UnderflowException $e )
					{
						$lastVote = time();
					}
					
					$info['last_vote'] = $lastVote;
				}
			}
			else
			{
				$info['poll_state']	= NULL;
				$info['last_vote']	= NULL;
			}
		}
		else
		{
			$info['poll_state']	= NULL;
			$info['last_vote']	= NULL;
		}
		
		if ( !isset( $info['views'] ) )
		{
			$info['views'] = 0;
		}
		
		if ( !isset( $info['approved'] ) )
		{
			$info['approved'] = 1;
		}
		
		/* Not Used */
		$info['author_mode'] = 0;
		
		if ( !isset( $info['pinned'] ) )
		{
			$info['pinned'] = 0;
		}
		
		if ( isset( $info['moved_to'] ) )
		{
			if ( !is_array( $info['moved_to'] ) )
			{
				list( $topic, $forum ) = explode( '&', $info['moved_to'] );
			}
			else
			{
				list( $topic, $forum ) = $info['moved_to'];
			}
			
			try
			{
				$topic = $this->getLink( $topic, 'forums_topics' );
			}
			catch( \OutOfRangeException $e )
			{
				$topic = NULL;
			}
			
			try
			{
				$forum = $this->getLink( $forum, 'forums_forums' );
			}
			catch( \OutOfRangeException $e )
			{
				$forum = NULL;
			}
			
			if ( is_null( $topic ) OR is_null( $forum ) )
			{
				$info['moved_to'] = NULL;
			}
			else
			{
				$info['moved_to'] = $topic . '&' . $forum;
			}
		}
		else
		{
			$info['moved_to'] = NULL;
		}
		
		/* Can't know this */
		$info['topic_firstpost'] = 0;
		
		if ( !isset( $info['topic_queuedposts'] ) )
		{
			$info['topic_queuedposts'] = 0;
		}
		
		if ( isset( $info['topic_open_time'] ) )
		{
			if ( $info['topic_open_time'] instanceof \IPS\DateTime )
			{
				$info['topic_open_time'] = $info['topic_open_time']->getTimestamp();
			}
		}
		else
		{
			$info['topic_open_time'] = 0;
		}
		
		if ( isset( $info['topic_close_time'] ) )
		{
			if ( $info['topic_close_time'] instanceof \IPS\DateTime )
			{
				$info['topic_close_time'] = $info['topic_close_time']->getTimestamp();
			}
		}
		else
		{
			$info['topic_close_time'] = 0;
		}
		
		if ( !isset( $info['topic_rating_total'] ) )
		{
			$info['topic_rating_total'] = 0;
		}
		
		if ( !isset( $info['topic_rating_hits'] ) )
		{
			$info['topic_rating_hits'] = 0;
		}
		
		if ( !isset( $info['title_seo'] ) )
		{
			$info['title_seo'] = \IPS\Http\Url::seoTitle( $info['title'] );
		}
		
		if ( isset( $info['moved_on'] ) )
		{
			if ( $info['moved_on'] instanceof \IPS\DateTime )
			{
				$info['moved_on'] = $info['moved_on']->getTimestamp();
			}
		}
		else
		{
			$info['moved_on'] = 0;
		}
		
		if ( !isset( $info['topic_archive_status'] ) )
		{
			$info['topic_archive_status'] = 0;
		}
		
		if ( isset( $info['last_real_post'] ) )
		{
			if ( $info['last_real_post'] instanceof \IPS\DateTime )
			{
				$info['last_real_post'] = $info['last_real_post']->getTimestamp();
			}
		}
		else
		{
			$info['last_real_post'] = $info['last_post'];
		}
		
		/* Can't knew these */
		$info['topic_answered_pid']	= 0;
		$info['popular_time']		= 0;
		
		if ( !isset( $info['featured'] ) )
		{
			$info['featured'] = 0;
		}
		
		if ( !isset( $info['question_rating'] ) )
		{
			$info['question_rating'] = NULL;
		}
		
		if ( !isset( $info['topic_hiddenposts'] ) )
		{
			$info['topic_hiddenposts'] = 0;
		}
		
		$id = $info['tid'];
		unset( $info['tid'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'forums_topics', $info );
		$this->addLink( $inserted_id, $id, 'forums_topics' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a Forum Post
	 *
	 * @param	array			$info	Data to insert
	 * @return	integer|boolean	The ID of the newly inserted post, or FALSE on failure.
	 */
	public function convertForumsPost( $info=array() )
	{		
		if ( !isset( $info['append_edit'] ) )
		{
			$info['append_edit'] = 0;
		}
		
		if ( isset( $info['edit_time'] ) )
		{
			if ( $info['edit_time'] instanceof \IPS\DateTime )
			{
				$info['edit_time'] = $info['edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['edit_time'] = NULL;
		}
		
		
		if ( !isset( $info['author_name'] ) )
		{
			$author = \IPS\Member::load( $info['author_id'] );
			
			if ( $author->member_id )
			{
				$info['author_name'] = $author->name;
			}
		}
		
		if ( !isset( $info['ip_address'] ) OR filter_var( $info['ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['post_date'] ) )
		{
			if ( $info['post_date'] instanceof \IPS\DateTime )
			{
				$info['post_date'] = $info['post_date']->getTimestamp();
			}
		}
		else
		{
			$info['post_date'] = time();
		}
		
		if ( !isset( $info['queued'] ) )
		{
			$info['queued'] = 0;
		}
		
		if ( !isset( $info['new_topic'] ) )
		{
			$info['new_topic'] = 0;
		}
		
		if ( !isset( $info['edit_name'] ) )
		{
			$info['edit_name'] = NULL;
		}
		
		/* Meh */
		$info['post_key'] = 0;
		
		if ( !isset( $info['post_htmlstate'] ) )
		{
			$info['post_htmlstate'] = 0;
		}
		
		if ( !isset( $info['post_edit_reason'] ) )
		{
			$info['post_edit_reason'] = '';
		}
		
		/* The Bit Options contain where or not this post is marked as best answer. Set a flag accordingly so we can update the topic later. */
		$isBestAnswer	= FALSE;
		$bitoptions		= 0;
		if ( isset( $info['post_bwoptions'] ) )
		{
			foreach( \IPS\forums\Topic\Post::$bitOptions['post_bwoptions']['post_bwoptions'] AS $key => $value )
			{
				if ( isset( $info['post_bwoptions'][$key] ) AND $info['post_bwoptions'][$key] )
				{
					$bitoptions += $value;
					if ( $key === 'best_answer' )
					{
						$isBestAnswer = TRUE;
					}
				}
			}
			$info['post_bwoptions'] = $bitoptions;
		}
		
		if ( isset( $info['pdelete_time'] ) )
		{
			if ( $info['pdelete_time'] instanceof \IPS\DateTime )
			{
				$info['pdelete_time'] = $info['pdelete_time']->getTimestamp();
			}
		}
		else
		{
			$info['pdelete_time'] = 0;
		}
		
		/* Are these even used yet? */
		if ( !isset( $info['post_field_int'] ) )
		{
			$info['post_field_int'] = 0;
		}
		
		if ( !isset( $info['post_field_t1'] ) )
		{
			$info['post_field_t1'] = NULL;
		}
		
		if ( !isset( $info['post_field_t2'] ) )
		{
			$info['post_field_t2'] = NULL;
		}
		
		$id = $info['pid'];
		unset( $info['pid'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'forums_posts', $info );
		$this->addLink( $inserted_id, $id, 'forums_posts' );
		
		$topicUpdate = array();
		
		if ( $info['new_topic'] == 1 )
		{
			$topicUpdate['topic_firstpost'] = $inserted_id;
		}
		
		if ( $isBestAnswer == TRUE )
		{
			$topicUpdate['topic_answered_pid'] = $inserted_id;
		}
		
		if ( count( $topicUpdate ) )
		{
			\IPS\Db::i()->update( 'forums_topics', $topicUpdate, array( "tid=?", $info['topic_id'] ) );
		}
		
		return $inserted_id;
	}
	public function addLink($ipb_id, $foreign_id, $type, $dupe='0')
	{
		// New table switching device - makes things LOTS faster.
		switch( $type )
		{
			case 'posts':
				$table = 'convert_link_posts';
				break;
	
			case 'topics':
				$table = 'convert_link_topics';
				break;
	
			case 'pms':
			case 'pm_posts':
			case 'pm_maps':
				$table = 'convert_link_pms';
				break;
	
			default:
				$table = 'convert_link';
				break;
		}
	
		// Setup the insert array with link values
		$insert_array = array( 'ipb_id'		=> $ipb_id,
							   'foreign_id' => $foreign_id,
							   'type'		=> $type,
							   'duplicate'	=> $dupe,
							   'app'		=> 'phpbb');
	
		// Insert the link into the database
		\IPS\Db::i()->insert( $table, $insert_array );
	
		// Cache the link
		$this->linkCache[$type][$foreign_id] = $ipb_id;
	}

	/**
	 * Get Link
	 *
	 * @access	public
	 * @param	integer		Foreign ID
	 * @param	string		Type
	 * @param	boolean		If true, will return false on error, otherwise will display error
	 * @param 	boolean		If true, will check parent app's history instead of own
	 * @return 	integer		IPB's ID
	 **/
	public function getLink($foreign_id, $type, $ret=false, $parent=false)
	{
		if (!$foreign_id or !$type)
		{
			if ($ret)
			{
				return false;
			}
			parent::sendError("There was a problem with the converter - could not get valid link: {$type}:{$foreign_id}");
		}
		if ( isset($this->linkCache[$type][$foreign_id]) )
		{
			return $this->linkCache[$type][$foreign_id];
		}
		else
		{
			// New table switching device - makes things LOTS faster.
			switch( $type )
			{
				case 'posts':
					$table = 'convert_link_posts';
					break;
	
				case 'topics':
					$table = 'convert_link_topics';
					break;
	
				case 'pms':
				case 'pm_posts':
				case 'pm_maps':
					$table = 'convert_link_pms';
					break;
	
				default:
					$table = 'convert_link';
					break;
			}
	
			// Parent?
			if ( $parent && $this->app['parent'] == 'self' )
			{
				$this->linkCache[$type][$foreign_id] = $foreign_id;
				return $foreign_id;
			}
	
			$appid = ($parent) ? $this->app['parent'] : $this->app['app_id'];
		//	$row = $this->DB->buildAndFetch( array( 'select' => 'ipb_id', 'from' => $table, 'where' => "foreign_id='{$foreign_id}' AND type='{$type}' AND app={$appid}" ) );
	
		//	if(!$row)
		//	{
				return false;
		//	}
	
			$this->linkCache[$type][$foreign_id] = $row['ipb_id'];
			return $row['ipb_id'];
		}
	}
}
for($i = 0; $i <= $last_thread; $i++){
	$convert = new Proboards();
	$convert->getPost('0',$i);
}
?>
