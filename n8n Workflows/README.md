# n8n workflow imports

The workflow exports in this directory intentionally do not contain live API tokens. Configure these environment variables on the n8n host before activating the workflows:

- `META_MESSENGER_ACCESS_TOKEN`
- `META_MESSENGER_COMMENT_ACCESS_TOKEN`
- `META_MESSENGER_IMAGE_ACCESS_TOKEN`
- `META_WHATSAPP_ACCESS_TOKEN`

The exports are inactive templates: stored n8n credential references, instance/workflow IDs, pinned sample data, and generated webhook IDs have been removed. Webhook trigger paths use `configure-*-webhook` placeholders; replace every placeholder with a unique path before activation. Reconnect each credential in the destination instance, then verify the generated production and test webhook URLs.

Restart n8n after changing environment variables so `$env` expressions resolve correctly.

Do not commit exported access tokens, authorization headers, passwords, or private keys. Run a secret scan before publishing updated workflow exports.
