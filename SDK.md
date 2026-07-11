# SDK.md

Technical reference for APIs, webservices, and SDKs exposed by AsgardCRM.

Nothing has been built yet under this scope — no API routes, webservices, or SDK have been created so far (auth is session/cookie-based web login only, see DOCUMENTATION.md).

This file will be populated per-endpoint/per-service as they're built. Expected shape per entry once work starts:

```
## <Resource/Service name>

- base path / route prefix
- auth method (guard, token type, scopes/permissions required via Just A Gate)
- endpoints: method, path, request shape, response shape, error cases
- versioning scheme (if any)
- rate limiting (if any)
- example request/response
```
