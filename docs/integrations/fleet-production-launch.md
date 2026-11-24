# LOG1–LOG2 Fleet Production Launch

## Production contract

- Delivery list: `GET https://supply.djnrmhs.com/api/v1/integrations/fleet/deliveries`
- Detail lookup: `GET https://supply.djnrmhs.com/api/v1/integrations/fleet/deliveries/{delivery_id}`
- Authentication: HTTPS with `X-API-Key`.
- List polling: every 2 minutes, pages via `?page=2` and Laravel `next_page_url`; page size defaults to 25 and is capped at 100.
- Only fully issued requisitions explicitly marked `fleet` with a destination receiving point are listed. Self-pickup and other arrangements are excluded. Acknowledged Fleet deliveries remain available through lookup.
- The delivery response includes the receiving point, optional off-site address, named recipient, optional phone, and optional preferred time window in the configured HIMS timezone.
- This is a read-only LOG2 pull. LOG2's proposed transportation-status endpoint is not connected by this launch and remains a separate follow-up once LOG2 provides its live URL and final contract.

## Coordinated go-live sequence

1. Deploy the current API contract to **staging**, including the delivery-arrangement migration. Reseed the synthetic Fleet data with `FleetIntegrationStagingSeeder` because pre-migration rows are intentionally unclassified.
2. Have LOG2 re-run list, pagination, and detail checks against the updated staging response. Confirm that the new `delivery_method`, destination and recipient fields, and time-window object are accepted. Confirm that a self-pickup requisition is absent from the list.
3. Create a **new production-only** high-entropy API key. Transfer the plaintext directly to LOG2 through the hospital-approved secret channel. Do not reuse the staging key or paste the production key in chat, email, URL, source control, or logs.
4. Store only the lowercase SHA-256 hexadecimal digest in HostForge's **production** environment as the Secret `FLEET_INTEGRATION_API_KEY_SHA256`. Do not change the Staging-scoped value while configuring Production.
5. Merge/push the approved changes to the production deployment branch. Confirm the additive migration has completed successfully and the production deployment passes its health check before enabling the client poller.
6. Verify the production URL over HTTPS: no key returns `401`, the production key returns `200`, and a known synthetic or approved low-risk Fleet requisition appears with the expected location and quantities. Verify the detail endpoint and page 2 when enough approved records exist. Do not use real patient data as test fixtures.
7. Ask LOG2 to enable polling at two-minute intervals only after the production smoke check passes. Monitor `401`, `429`, and `5xx` responses and coordinate key rotation directly if needed.

## PowerShell key generation (run locally)

Run this on a trusted workstation. Keep the `API KEY` output for the secure transfer to LOG2 and enter only `SHA-256` in HostForge. Never paste either value into source control or this chat.

```powershell
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$bytes = New-Object byte[] 32
$rng.GetBytes($bytes)
$key = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+','-').Replace('/','_')
$sha = [System.Security.Cryptography.SHA256]::Create()
$hashBytes = $sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($key))
$hash = [BitConverter]::ToString($hashBytes).Replace('-','').ToLowerInvariant()
"API KEY: $key"
"SHA-256: $hash"
$rng.Dispose()
$sha.Dispose()
```

## Go-live blockers to confirm

- LOG2 re-accepts the updated staging response contract after destination and delivery-method filtering are introduced.
- HostForge production HTTPS domain resolves to the intended HIMS application and its production environment can securely store the key digest.
- A production key has been exchanged through an approved secure channel and configured on both sides.
- The migration and deployment branch contain only changes intended for the live site. This workspace currently has other uncommitted work; review the full Git change list before pushing or deploying.
- Any existing production requisitions that predate the new fields remain unclassified and will not be sent to Fleet. Logistics 1 must explicitly identify any outstanding requisitions that should use Fleet; do not bulk-default them to Fleet.
