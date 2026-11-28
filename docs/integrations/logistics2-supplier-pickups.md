# Logistics2 supplier pickup API

Base URL: `https://supply.djnrmhs.com/api/v1/integrations/fleet`

All requests use HTTPS and the existing HIMS Fleet integration key in the `X-API-Key` header. The API is rate-limited to 60 requests per minute. Do not put the key in a URL or logs.

## Poll pickup requests

`GET /supplier-pickups?per_page=25`

Returns paginated supplier ASNs with status `ready_for_pickup`. Follow the returned `next_page_url` when present. Polling every two minutes is the proposed interval, subject to Logistics2's confirmation.

Example pickup record:

```json
{
  "delivery_id": "ASN-20261127-TTV94CDF",
  "delivery_type": "supplier_pickup",
  "asn_number": "ASN-20261127-TTV94CDF",
  "purchase_order_number": "PO-20261127-170728-XAZU",
  "status": "ready_for_pickup",
  "requested_at": "2026-11-27T09:00:00.000000Z",
  "actual_pickup_date": null,
  "expected_arrival_date": "2026-12-31",
  "actual_delivery_date": null,
  "carrier": "HIMS LOGISTICS",
  "tracking_number": null,
  "waybill_number": null,
  "driver": null,
  "pickup": {
    "location": "JRD Supplies - Registered Address",
    "address": "Supplier's registered pickup address",
    "contact": {"name": "Supplier contact", "phone": "Supplier phone"}
  },
  "destination": {"location": "Central Hospital Receiving Dock"},
  "supplier": {"name": "JRD Supplies"},
  "items": [
    {"sku": "FCAST-ALCOHOL-500", "name": "Isopropyl Alcohol 70% 500 mL", "quantity": 18, "unit": "Bottle", "pickup_location": "JRD Supplies - Registered Address"}
  ]
}
```

The actual response contains the supplier's registered contact and address. Keep that operational information within the authorized Logistics2 workflow.

## Read an ASN or check its current status

`GET /supplier-pickups/{asn_number}`

Returns the current record in a `data` object. Logistics2 can use this to recover status after a callback retry or missed poll.

## Report pickup and arrival

`PATCH /supplier-pickups/{asn_number}/status`

Use the same `X-API-Key` header. Update statuses in order:

1. `picked_up` when the driver collects the goods from the supplier. Required: `actual_pickup_date` (`YYYY-MM-DD`) and `carrier_name`. Optional: `tracking_number`, `waybill_number`, `vehicle_plate_number`, `driver_name`, and `driver_contact`.
2. `arrived_at_dock` after the shipment physically reaches the HIMS receiving dock. Required: `actual_delivery_date` (`YYYY-MM-DD`) and `package_condition`; optional: `temp_min`, `temp_max`, `temp_logger_serial`, and `notes`.

Example pickup callback:

```json
{
  "status": "picked_up",
  "actual_pickup_date": "2026-11-28",
  "carrier_name": "HIMS LOGISTICS",
  "tracking_number": "LOG2-12345",
  "driver_name": "Driver Name",
  "driver_contact": "Driver Contact"
}
```

Example dock-arrival callback:

```json
{
  "status": "arrived_at_dock",
  "actual_delivery_date": "2026-11-29",
  "package_condition": "good_order",
  "notes": "Seal intact"
}
```

`package_condition` accepts `good_order`, `damaged_packaging`, `tampered_seal`, or `seal_intact`. Callbacks are safe to retry after a successful pickup or arrival. An out-of-order arrival is rejected with HTTP 409. Validation errors return HTTP 422; missing or invalid API keys return HTTP 401; not-found ASNs return HTTP 404; excessive requests return HTTP 429.

An ASN stays `ready_for_pickup` until the pickup callback succeeds. HIMS then records it as `picked_up` / in transit. Only the arrival callback marks it `arrived_at_dock`; HIMS dock receiving and the GRN flow remain unavailable until that point.
