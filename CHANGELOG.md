ClaudetteMcp 0.5 - 2026.11.23
===========================================================
* [new] Add tool update_media (title, alternate text, description, privacy), list_media gives them too
* [new] Add tool update_category (title, description, parent, URL kept), list_categories gives description
* [new] Add tools list_links and set_link for the plugin blogroll
* [new] Add tool rename_tag (rename or merge a tag, content administrator), only on entries types open to tools
* [new] Add tool get_compatibility_audit to read the DotclearWatch audit (super admin), summary of modules with findings
* [new] Let a plugin add its entries statuses to entries tools (behavior ClaudetteMcpAddPostStatus)
* [new] Tools families: a key gives only some families of tools
* [lang] French names of the families of tools
* [docs] Document tools from Dotclear core and distributed plugins, and tools families

ClaudetteMcp 0.4 - 2026.11.17
===========================================================
* [new] Let a plugin add its entries type to entries tools (behavior ClaudetteMcpAddPostType)
* [docs] Entries tools only see posts and pages

ClaudetteMcp 0.3.1 - 2026.11.14
===========================================================
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

