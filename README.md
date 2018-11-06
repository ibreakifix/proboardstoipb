# Proboards to Invision Community
Proboards to IPB Conversion via Mobiquo Scraping

This script takes content from your proboards forum via Taptalk API, converts the BBCode (most of it) into HTML compatible with IPS's requirements and inserts it into the IPS database. 

You can define an array of user ids or forum ids to remap content to the correct users / forums on your new board. 

The script requires you to have a cookie from a valid tapatalk session, take the cookie using Charles Proxy (or similar) and paste it into the variable in the script.

This script is nowhere close to production ready, it was written quickly for one forum migration of about 30,000 posts. It worked well enough for our purposes in the current forum and will no longer be developed.

- After running this script:
  - Recount your user's content count in the Members panel of the Admin CP
  - Rebuild your search index
  - Add and delete a post to one of the forum's posts to refresh the post count and "latest post" displayed on the forum index
  
- Known Issues:
  - Last post date does not get updated properly for every thread, similarly the last post user is sometimes not updated. This can be resolved with some SQL queries to update the databases
  - Some BBCode does not parse properly, namely tables and some nested quotes. You can add your own regex to the showBBCodes function to mitigate this.
