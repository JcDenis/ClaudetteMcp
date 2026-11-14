# ClaudetteMcp

Notes for AI assistants working on the Dotclear plugin ClaudetteMcp.

**Read first** the shared conventions of the author's Dotclear modules:
https://raw.githubusercontent.com/JcDenis/gh2da/master/CONVENTIONS.md
(if a web tool refuses the address, use `curl -s` on it from the shell).
The rules below take precedence for this module.

## Module

Connect an AI assistant to your blog through MCP It adds a [Model Context Protocol](https://modelcontextprotocol.io) server to a blog: an AI assistant (Claude or any MCP client) can then use tools to work on the blog,.

Admin page. Public side: behaviors.

Required Dotclear and PHP versions: see `_define.php`.

## Structure

* `src/Backend.php`: admin side: menu entry, favorites, behaviors registration.
* `src/Frontend.php`: public side: behaviors, template tags and URL handlers registration.
* `src/Manage.php`: admin page of the module.
* `src/McpEndpoint.php`: API endpoint.
* `src/McpKey.php`: access key descriptor.
* `src/McpKeys.php`: keys are stored in Dotclear core credential table.
* `src/McpMediaLink.php`: a media link type is a way to link a media to an entry (table post_media of Dotclear core).
* `src/McpMediaLinks.php`: dotclear core only knows the link type "attachment".
* `src/McpServer.php`: minimal stateless Model Context Protocol server.
* `src/McpTool.php`: a tool is a function the AI assistant can call.
* `src/McpToolException.php`: message of this exception is sent to the AI assistant.
* `src/McpTools.php`: tools stack.
* `src/My.php`: module helpers (`My::id()`, settings, permissions).
* `src/ThirdParty/FeaturedMedia.php`: the plugin featuredMedia (by Franck Paul) links one media to an entry.
* `src/ThirdParty/ThirdParty.php`: tools only use Dotclear core and its distributed plugins.
* `src/Tool/AttachMedia.php`: tool: link a media to an entry.
* `src/Tool/CommentTool.php`: in frontend context Dotclear only gives published comments.
* `src/Tool/CreateCategory.php`: tool: create a category.
* `src/Tool/CreatePost.php`: tool: create an entry.
* `src/Tool/DetachMedia.php`: tool: unlink a media from an entry.
* `src/Tool/GetBlog.php`: tool: get blog information.
* `src/Tool/GetPost.php`: tool: get an entry.
* `src/Tool/ListCategories.php`: tool: list categories.
* `src/Tool/ListComments.php`: tool: list comments.
* `src/Tool/ListMedia.php`: tool: list media.
* `src/Tool/ListPosts.php`: tool: list entries.
* `src/Tool/ListTags.php`: tool: list tags.
* `src/Tool/MediaTool.php`: media tools helper.
* `src/Tool/PostTool.php`: entries tools helper.
* `src/Tool/SetCommentStatus.php`: tool: change the status of a comment.
* `src/Tool/SetPostStatus.php`: tool: change the status of an entry.
* `src/Tool/UpdatePost.php`: tool: update an entry.
* `src/Tool/UploadMedia.php`: tool: upload a media.
* Behaviors used: `ClaudetteMcpAddMediaLink`.
* Requires ApiServer (`requires` in `_define.php`).

## Pitfalls

* A key never gives more than the Dotclear permissions of its user. Tools run in frontend context, where the core checks nothing: every tool checks permissions itself.
* No tool deletes content, touches blog settings, users, plugins or keys. Keys are stored in the core table `credential` without changing its structure, one key per blog.
* Its own tools only use the Dotclear core and its distributed plugins. The author's plugins carry their own tools; plugins of other authors who will not do it go in `src/ThirdParty/`, written with ClaudetteMcp behaviors only, without calling their classes.
* Each tool must be validated by the user when the connector is (re)connected: keep the number of tools small.
* CI installs ApiServer from the author's repositories, then from franck-paul's.

## Testing

* Check the admin page, in English and French.
* CI browses the common admin and public pages; extra pages can be listed in `.github/dotclear-test.txt`.

## Design

Read `CONCEPTION.md` (in French) before changing the design, the data model or the tools,
and update it with the change.
