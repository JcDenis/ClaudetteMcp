ClaudetteMcp 0.3.1 - 2026.11.14
===========================================================
* Clean CHANGELOG
* [docs] Add a user guide in French, with screenshots
* [docs] Add admin help in English and French

ClaudetteMcp 0.3 - 2026.11.12
===========================================================
* Add tools for pages, media, comments, categories and notes
* Add pagination to list_media
* Add behavior ClaudetteMcpAddMediaLink for media link types
* Add featuredMedia support in ThirdParty directory

ClaudetteMcp 0.2 - 2026.11.11
===========================================================
* Send access key in a request header rather than in connector address
* Answer handshake without access key, clients check connector address before sending headers
* Use X-Api-Key request header, Claude connectors only send approved header names
* Design: header key checked with Claude
* Tell in README that Claude connector needs manual configuration

ClaudetteMcp 0.1 - 2026.11.11
===========================================================
* First release
* Add MCP server as an endpoint of ApiServer
* Add access keys management (one key per user and per blog)
* Add reading tools get_blog, list_posts, get_post, list_categories, list_tags, list_media
* Add writing tools create_post, update_post, set_post_status

