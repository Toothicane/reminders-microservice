# Reminders API

A small Symfony JSON API for creating and managing user-owned reminders. The local development setup runs the app and PostgreSQL in Docker Compose.

## Requirements

- Docker Engine with the Docker Compose plugin
- GNU Make

No host PHP, Composer, PostgreSQL server, or database setup is required.

## Start

From the repository root, run:

```sh
make start
```

This builds the PHP image and starts the app and PostgreSQL in the background. On first startup, the app waits for PostgreSQL, creates the development and test databases, applies the committed migrations to both, and then starts the HTTP server.

The API is available at <http://localhost:8000>. Startup includes the initial image build and database migration, so the server may take a short while to become ready. To follow startup output, run:

```sh
docker compose logs -f app
```

Press `Ctrl+C` to stop following logs; the containers continue running.

## API Examples

Every endpoint requires an `X-User-Id` header. Reminders created with one user ID are only visible to requests using that same ID. The examples use `demo-user` and assume the server is running.

### Create a reminder

```sh
curl -i -X POST http://localhost:8000/reminders \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-User-Id: demo-user' \
  -d '{"title":"Book an appointment","notes":"Call in the morning","dueAt":"2030-04-05T12:30:00+00:00"}'
```

This returns `201 Created` with a JSON body containing a `reminder` object, including its generated `id`, `userId`, `title`, `notes`, `dueAt`, `status`, `createdAt`, and `updatedAt` fields. Copy the returned `id` for the following examples.

```sh
REMINDER_ID='paste-the-returned-id-here'
```

### List reminders

```sh
curl -i http://localhost:8000/reminders \
  -H 'Accept: application/json' \
  -H 'X-User-Id: demo-user'
```

This returns `200 OK` with a JSON body containing an `items` array of the requesting user's reminders. Filter by status with `active` or `done`:

```sh
curl -i 'http://localhost:8000/reminders?status=active' \
  -H 'Accept: application/json' \
  -H 'X-User-Id: demo-user'
```

The filtered list also returns `200 OK` with the matching reminders in `items` (an empty array if there are no matches).

### Get one reminder

```sh
curl -i "http://localhost:8000/reminders/${REMINDER_ID}" \
  -H 'Accept: application/json' \
  -H 'X-User-Id: demo-user'
```

This returns `200 OK` with a JSON body containing the requested reminder under `reminder`, using the same fields as the create response.

### Update a reminder

```sh
curl -i -X PATCH "http://localhost:8000/reminders/${REMINDER_ID}" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-User-Id: demo-user' \
  -d '{"title":"Updated appointment","notes":"Call after 9 AM","dueAt":"2030-04-06T09:00:00+00:00"}'
```

This returns `200 OK` with a JSON body containing the updated reminder under `reminder`.

### Mark a reminder as done

```sh
curl -i -X POST "http://localhost:8000/reminders/${REMINDER_ID}/done" \
  -H 'Accept: application/json' \
  -H 'X-User-Id: demo-user'
```

This returns `200 OK` with a JSON body containing the updated reminder under `reminder`; its `status` is `done`.

### Delete a reminder

```sh
curl -i -X DELETE "http://localhost:8000/reminders/${REMINDER_ID}" \
  -H 'X-User-Id: demo-user'
```

Successful deletion returns `204 No Content` with an empty response body.

## Tests

With the services running, execute the PHPUnit suite inside the app container:

```sh
make test
```

The test database is created and migrated during app startup.

With the app container running, run the code-quality checks inside it:

```sh
make lint
```

GitHub Actions runs these checks and the PHPUnit suite on pushes to `main` and pull requests targeting `main`.

## Stop and Data

Stop the app and PostgreSQL with:

```sh
make stop
```

The PostgreSQL data is stored in a named Docker volume and is preserved across `make stop` and later starts. To intentionally remove the database and all saved reminders, run:

```sh
docker compose down -v
```

## Ports

By default, the API is bound to `127.0.0.1:8000`. PostgreSQL is only available inside the private Compose network, so it does not conflict with a PostgreSQL server already running on the host. Override the API host port if 8000 is already in use:

```sh
APP_PORT=8080 make start
```

With that example, the API is at <http://localhost:8080>. The app connects to PostgreSQL over the private Compose network.