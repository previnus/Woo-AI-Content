# Woo AI Content Generator

Generate a full product description, a short description, and suggested SEO copy from the standard WooCommerce product editor.

By [Previn Kalisetty Appadu](https://previn.me).

## Requirements

- WordPress 6.5 or newer with WooCommerce active and the standard product editor enabled.
- PHP 8.3 or newer with cURL enabled and direct outbound HTTPS over IPv4.
- An API key and model for OpenAI or another OpenAI-compatible Chat Completions provider.

## Install

1. Download `woo-ai-content-generator.zip` from [GitHub Releases](https://github.com/previnus/Woo-AI-Content/releases).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**. Choose the ZIP, select **Install Now**, and activate the plugin.
3. To upgrade, upload the new release ZIP and choose **Replace current with uploaded**. WordPress keeps the existing settings.

## Configure the AI provider

1. As a site administrator, open **WooCommerce → AI Content Generator**.
2. For OpenAI, keep `https://api.openai.com/v1/chat/completions` and `gpt-4.1-mini`, or enter a different Chat Completions model available to your API account.
3. For another provider, enter its full Chat Completions URL and model ID. The URL must use a public hostname and HTTPS on port 443. Use an endpoint that accepts bearer authentication, system and user messages, and `max_tokens`, and returns text in `choices[0].message.content`.
4. Enter the provider's API key, click **Save Changes**, then open a product and generate a test draft. Leave the key field blank on later saves to keep the saved key. Changing the endpoint clears the old key unless you supply a replacement.

## Generate product copy

1. Open a product under **Products** and find **AI Content Generator** on the edit screen. Enter the product name and any relevant facts or writing preferences. Existing descriptions and saved categories and visible attributes provide context.
2. Click **Generate suggestions** and review the draft. Each description has a button to insert it into the corresponding editor. Inserting into a populated field asks before replacing its current text. Save or update the product as usual when you are ready.
3. Copy the suggested SEO title, meta description, and keywords into the SEO tool you use. The plugin only previews these suggestions.

## Credentials and data handling

Only site administrators can change the endpoint or key. A key entered in the settings is stored in the site's WordPress database and is never returned to the browser. To keep it outside the database, define `WOO_AI_CONTENT_API_KEY` in `wp-config.php` from a server-managed secret. This locks the endpoint to OpenAI unless you also define `WOO_AI_CONTENT_API_ENDPOINT` in `wp-config.php`. Remove any saved database key from the settings before switching to a server-managed key.

Generation requires a valid WordPress nonce and permission to edit the specific product. Each click sends the displayed product name and descriptions, saved categories and visible attributes, writing notes, and site language to the configured provider. The provider's terms and usage charges apply. Generated copy stays in the browser until you insert it and save the product. The plugin does not log prompts, responses, or keys.

Requests use a bounded timeout, response size, and output token count, with a site-wide cooldown of 10 seconds. The HTTPS connection is pinned to a verified public IPv4 address and uses certificate validation. Redirects and proxies are disabled to keep credentials on the configured connection. WordPress's external HTTP blocking policy is respected. Deleting the plugin removes its settings and cooldown data from WordPress.

## Troubleshooting

| Message | What to check |
| --- | --- |
| The provider rejected the key or model access | Confirm that the API key is active and its project has access to the configured model. |
| The request failed or timed out | Check the endpoint URL, cURL extension, DNS, and direct outbound HTTPS access from the WordPress server. |
| The provider did not return a complete set of suggestions | Confirm that the endpoint uses the Chat Completions response format and the model can follow JSON output instructions. |
| Generation is temporarily limited | Wait 10 seconds before generating again. For a provider quota error, check your provider account. |

The plugin is licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
