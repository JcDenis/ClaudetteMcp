# README

[![Release](https://img.shields.io/github/v/release/JcDenis/ClaudetteMcp?color=lightblue)](https://github.com/JcDenis/ClaudetteMcp/releases)
![Date](https://img.shields.io/github/release-date/JcDenis/ClaudetteMcp?color=red)
[![Dotclear](https://img.shields.io/badge/dotclear-v2.40-137bbb.svg)](https://dotclear.org/telechargement)
[![License](https://img.shields.io/github/license/JcDenis/ClaudetteMcp?color=white)](https://github.com/JcDenis/ClaudetteMcp/blob/master/LICENSE)

## ABOUT

_ClaudetteMcp_ is a plugin for the open-source web publishing software called [Dotclear](https://www.dotclear.org).

> Connect an AI assistant to your blog through MCP

It adds a [Model Context Protocol](https://modelcontextprotocol.io) server to a blog:
an AI assistant (Claude or any MCP client) can then use tools to work on the blog,
without a browser and without knowing any password.

**This is an early version: tools read the blog, create, update and publish entries, upload media and moderate comments, but never delete anything.**
See [CONCEPTION.md](CONCEPTION.md) (in French) for the design and the roadmap.

## REQUIREMENTS

* Dotclear 2.40
* PHP 8.3+
* Plugin [ApiServer](https://github.com/JcDenis/ApiServer) 1.0+, active on the blog
* A blog publicly reachable through HTTPS

## USAGE

1. Install and enable the plugins _ApiServer_ and _ClaudetteMcp_.
2. Activate the API in blog settings, and give the permission "API client" to the users allowed to use it.
3. In the blog menu, open _Claudette MCP_ and create a key.
4. Add a custom connector (remote MCP server, no authentication) in your AI assistant, with the given address
   and the given request header `X-Api-Key`, which contains the key.

With Claude, the connector must be added through the manual configuration (authentication "No sign-in"),
as the server can not be checked before the request header is given.

The key is shown once and must be kept secret.
For an assistant which can not send request headers, a key can also be accepted in the connector address
(uncheck "Accept the key only from the request header"), but it can then end in the web server logs.
A key belongs to one user and one blog, it can be revoked at any time from the same page.
The assistant acts with the permissions of the user who created the key, never more.

## TOOLS

| Tool | Description |
|---|---|
| `get_blog` | Blog information and what the user is allowed to do |
| `list_posts` | List posts or pages, by status, category, tag or search |
| `get_post` | Read a post or a page with its excerpt, content, linked media and notes |
| `list_categories` | List categories |
| `list_tags` | List tags (plugin tags) |
| `list_media` | List a directory of the media library (media permission) |
| `list_comments` | List comments and trackbacks, by status or entry |
| `create_post` | Create a post or a page, never published (HTML or wiki) |
| `update_post` | Update a post or a page, without changing its status |
| `set_post_status` | Publish, schedule, unpublish a post or a page (publish permission) |
| `create_category` | Create a category (categories permission) |
| `upload_media` | Add a file to the media library, never replacing one (media permission) |
| `attach_media` | Link a media to an entry, as attachment (or as featured media with the plugin featuredMedia) |
| `detach_media` | Remove the link between a media and an entry |
| `set_comment_status` | Publish, unpublish or mark as junk a comment (publish permission) |

Pages need the plugin pages and its permission.
A key never gives more than the Dotclear permissions of its user: with an administrator account, the assistant can do
on contents what an administrator does. Prefer a key created with a dedicated user having only the permissions you need.
Comments are listed like in the blog administration (all of them for a content administrator, those of his entries for another user),
without the email and IP addresses of their authors.

Entries which are not published are readable only by their author or by a content administrator.
Writing tools are not available with a read only key, and need the same permissions as in the blog administration.

## DEVELOPERS

Other plugins can add tools with the behavior `ClaudetteMcpAddTool`:
append to the given `ArrayObject` an instance of a class extending `Dotclear\Plugin\ClaudetteMcp\McpTool`.

Tools run in frontend context, where Dotclear does not limit contents to user permissions:
each tool must check permissions by itself.

Other plugins can add a way to link a media to an entry with the behavior `ClaudetteMcpAddMediaLink`:
append to the given `ArrayObject` an instance of `Dotclear\Plugin\ClaudetteMcp\McpMediaLink`
(link type of the table `post_media`, description, and if an entry has only one media of this type).
The tools `attach_media`, `detach_media` and `get_post` then know this link type.

A tool working on entries can extend `Dotclear\Plugin\ClaudetteMcp\Tool\PostTool` to use the same rules as other tools:
`fetch()` gets an entry the user can see, `editable()` tells if he can update it.

A plugin should provide its own tools, in its own code: see the plugin periodical for an example.
ClaudetteMcp tools only use Dotclear core and its distributed plugins. The directory `src/ThirdParty`
holds the support of plugins of other authors, written with these behaviors only, as if these plugins provided it:
currently the featured media of the plugin featuredMedia.

## LINKS

* [License](https://github.com/JcDenis/ClaudetteMcp/blob/master/LICENSE)
* [Packages & details](https://github.com/JcDenis/ClaudetteMcp/releases)
* [Issues & security](https://github.com/JcDenis/ClaudetteMcp/issues)

## CONTRIBUTORS

* Jean-Christian Denis (author)
* Claude (AI assistant by Anthropic)

You are welcome to contribute to this code.
