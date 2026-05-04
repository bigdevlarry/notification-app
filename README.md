# Insider Notification Service

A notification API that supports mail, SMS, and push delivery. Notifications are queued and processed asynchronously with priority-based routing.

---

## Setup

```bash
cp .env.example .env
docker-compose up
```

Migrations, and seeder runs, and a queue worker picks up jobs on all three priority queues.

To run the all tests when the container is up, use the command:

```bash
docker-compose exec app php artisan test
```

---

## Architecture

`HTTP request -> NotificationController -> NotificationService -> Queue -> ProcessNotificationJob -> mail / sms / push`

**Key design decisions:**

- **Priority queues** — three separate queues (`high`, `normal`, `low`). The worker listens on all three in order.

- **Idempotency** — a hash of `recipient_id + channel + content + priority` is stored as `idempotency_key`. Duplicate requests return the existing record without re-queuing.

- **Batch processing** — `Bus::batch()` groups jobs by priority, so callbacks (`then`, `catch`) fire per-priority group and individual job failures.

- **Correlation ID** — every request gets an `X-Correlation-ID` (generated if not provided). It's threaded through the controller, service, and queued job so all log lines for a request share the same ID.

- **Status lifecycle** — `pending -> processing -> sent / failed`. Cancellation is only allowed from `pending`. Once a job picks up the notification it moves to `processing` immediately, so there's no race between cancel and send.

**Things I would change if I had more time:**

- **Add authentication and create a user flow :** — I would add a user model, and authenticate before sending notifications.
- 
- **Push and SMS share the same provider** — in reality they'd use different providers, The `ExternalNotificationProvider` would need to be split or made channel-aware.
---

## API

Base URL: `http://localhost:8000/api/v1`

There's an OpenAPI spec available at docs/openapi.yaml.

All responses include an `X-Correlation-ID` header. You can pass your own value in the request header or one will be generated automatically.

### Create a notification
NOTE: Recipient_id is always 1. Seeder has a single user setup

```
POST /notifications
```

```json
{
  "recipient_id": 1,
  "channel": "mail",
  "content": "Your order has been shipped.",
  "priority": "high"
}
```

For SMS or push, include `recipient_address`:

```json
{
  "recipient_id": 1,
  "channel": "sms",
  "content": "Your code is 4821.",
  "priority": "high",
  "recipient_address": "+905551234567"
}
```

### Create a batch

```
POST /notifications/batch
```

```json
{
  "notifications": [
    { "recipient_id": 1, "channel": "mail", "content": "Hello.", "priority": "normal" },
    { "recipient_id": 2, "channel": "sms",  "content": "Hi.",    "priority": "high", "recipient_address": "+905559876543" }
  ]
}
```

Returns a `batch_id` you can use to query or cancel the whole batch.

### Other endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/notifications` | List notifications (filterable by `status`, `channel`, `priority`, `recipient_id`, `from`, `to`) |
| GET | `/notifications/:id` | Get a single notification |
| DELETE | `/notifications/:id` | Delete a notification |
| PATCH | `/notifications/:id/cancel` | Cancel a pending notification |
| GET | `/notifications/status?id=` | Look up by ID |
| GET | `/notifications/status?batch_id=` | Look up all in a batch |
| PATCH | `/notifications/batch/:batchId/cancel` | Cancel all pending in a batch |
| GET | `/health` | Database and queue health check |
| GET | `/metrics` | Notification counts, success/failure rates, queue depth |

---

