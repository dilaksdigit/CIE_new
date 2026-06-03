# N8N Webhook HMAC Verification (P2.4)

PHP `ChannelDeployService` signs outbound webhooks with:

```
X-N8N-Signature: HMAC-SHA256(JSON body, N8N_WEBHOOK_SECRET)
```

## W6 / W5 — Add Code node after Webhook

Applies to **shopify_deploy**, **gmc_deploy**, W5, and W6 webhook triggers.

In n8n, insert a **Code** node immediately after the Webhook trigger:

```javascript
const crypto = require('crypto');
const secret = $env.N8N_WEBHOOK_SECRET;
const rawBody = JSON.stringify($json.body ?? $json);
const sig = $input.first().headers['x-n8n-signature'] || $input.first().headers['X-N8N-Signature'];
const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
if (!sig || sig !== expected) {
  throw new Error('Invalid webhook signature');
}
return $input.all();
```

Enable **Raw Body** on the webhook if your n8n version requires matching PHP's `json_encode($payload)` byte-for-byte.

## Evidence (staging)

- N8N execution log: rejected request without header returns error
- Valid signed request from `ChannelDeployService::deployToShopify` succeeds
