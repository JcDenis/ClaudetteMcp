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

**This is an early version: tools read the blog, create, update and publish entries, but never delete anything.**
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

The key is shown once and must be kept secret.
For an assistant which can not send request headers, a key can also be accepted in the connector address
(uncheck "Accept the key only from the request header"), but it can then end in the web server logs.
A key belongs to one user and one blog, it can be revoked at any time from the same page.
The assistant acts with the permissions of the user who created the key, never more.

## TOOLS

| Tool | Description |
|---|---|
| `get_blog` | Blog information and what the user is allowed to do |
| `list_posts` | List entries, by status, category, tag or search |
| `get_post` | Read an entry with its excerpt and content |
| `list_categories` | List categories |
| `list_tags` | List tags (plugin tags) |
| `list_media` | List a directory of the media library (media permission) |
| `create_post` | Create an entry, never published (HTML or wiki) |
| `update_post` | Update an entry, without changing its status |
| `set_post_status` | Publish, schedule, unpublish an entry (publish permission) |

Entries which are not published are readable only by their author or by a content administrator.
Writing tools are not available with a read only key, and need the same permissions as in the blog administration.

## DEVELOPERS

Other plugins can add tools with the behavior `ClaudetteMcpAddTool`:
append to the given `ArrayObject` an instance of a class extending `Dotclear\Plugin\ClaudetteMcp\McpTool`.

Tools run in frontend context, where Dotclear does not limit contents to user permissions:
each tool must check permissions by itself.

## LINKS

* [License](https://github.com/JcDenis/ClaudetteMcp/blob/master/LICENSE)
* [Packages & details](https://github.com/JcDenis/ClaudetteMcp/releases)
* [Issues & security](https://github.com/JcDenis/ClaudetteMcp/issues)

## CONTRIBUTORS

* Jean-Christian Denis (author)
* Claude (AI assistant by Anthropic)

You are welcome to contribute to this code.
