=== LLM BuddyPress Mentions ===
Contributors: cryptoball7
Tags: buddypress, mentions, ai, chatbot, llm
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Let users summon an LLM agent in BuddyPress by mentioning a chosen bot user (e.g., @grok). The bot posts a threaded reply with an LLM-generated answer.

== Description ==

This plugin listens for @mentions of a configured BuddyPress user. When detected in an activity update or comment, it sends the text (plus a small slice of thread context) to an LLM endpoint (OpenAI-compatible by default) and posts the response back to the thread as the bot user.

**Features**
* Choose any existing WordPress user to act as the bot (e.g., "grok").
* Works in BuddyPress activity updates & comments.
* Configurable endpoint/model/temperature/max tokens/system prompt.
* Lightweight; no JS required on the front-end.

**Notes**
* The plugin posts replies synchronously on save; if your site is high traffic, consider offloading to a queue/cron by forking `respond_in_activity` into a `wp_schedule_single_event` job.
* To support providers with different request/response shapes, use filters: `llmbpm_request_headers` and `llmbpm_request_body`.

== Installation ==

1. Upload the zip via **Plugins → Add New** and activate.
2. Ensure BuddyPress is active.
3. Go to **Settings → LLM Mentions**:
   * Pick the bot user.
   * Enter API key, endpoint (default is OpenAI chat completions), and a model.
   * Adjust temperature/max tokens/system prompt if desired.
4. In activity, type `@botname` in an update or comment. The bot will reply in the same thread.

== Frequently Asked Questions ==

= Does it support private messages? =
The 1.0.0 release focuses on activity updates/comments. You can extend it by hooking into `messages_message_sent` and posting a reply with the Messages API.

= Can it avoid loops? =
Yes; it ignores posts from the bot user itself.

= How do I switch to a different provider? =
Set a custom endpoint and transform headers/body via:
- `add_filter( 'llmbpm_request_headers', function( $headers ) { /* ... */ return $headers; } );`
- `add_filter( 'llmbpm_request_body', function( $body ) { /* ... */ return $body; } );`

== Changelog ==

= 1.0.0 =
* First public release.
