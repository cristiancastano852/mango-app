## MODIFIED Requirements

### Requirement: Resolución del tenant actual desde el subdominio

El sistema SHALL resolver la `Company` actual a partir del host de la petición HTTP usando un middleware en el grupo `web` que lee `$request->getHost()` y resuelve `Company` por `slug` igual a la primera etiqueta del host. La `Company` resuelta SHALL quedar disponible como "tenant actual" durante la petición.

**Business Rules:**
- El identificador de subdominio es el `companies.slug` (no se introduce columna nueva).
- Los hosts no-tenant NO resuelven a ningún tenant: el host público (`webplena.com`, `www`) y el host de administración de plataforma (`admin.webplena.com`). Los subdominios reservados incluyen al menos `www` y `admin`.
- Un subdominio que no corresponde a ninguna `Company` SHALL producir 404.
- La resolución ocurre antes de la autorización de la ruta, de modo que el gate de login y el aislamiento de datos puedan usar el tenant.
- **El módulo kiosk SHALL depender de la resolución del subdominio para obtener la `Company`; no se acepta la `Company` como parámetro de ruta.**

**Authorization:**
- Aplica a todas las peticiones `web`. No depende del rol; el tenant existe (o no) independientemente de quién esté autenticado.

#### Scenario: Subdominio válido resuelve a su empresa
- **WHEN** llega una petición a `restaurantex.webplena.com` y existe una `Company` con `slug = "restaurantex"`
- **THEN** el tenant actual de la petición es esa `Company`

#### Scenario: Subdominio inexistente responde 404
- **WHEN** llega una petición a `noexiste.webplena.com` y ningún `Company.slug` es `"noexiste"`
- **THEN** la respuesta es 404

#### Scenario: Host público no fija tenant
- **WHEN** llega una petición a `webplena.com`
- **THEN** no hay tenant actual y la petición se sirve como host público (landing)

#### Scenario: Host de administración no fija tenant
- **WHEN** llega una petición a `admin.webplena.com`
- **THEN** no hay tenant actual y la petición se sirve como host de super-admin

#### Scenario: Subdominio reservado no se interpreta como slug
- **WHEN** llega una petición a `www.webplena.com` o `admin.webplena.com`
- **THEN** no se intenta resolver una `Company` con `slug = "www"` ni `"admin"` y la petición se trata como host no-tenant

#### Scenario: Kiosk en subdominio válido sirve la empresa correcta
- **WHEN** llega una petición a `GET acme.mango-app.test/kiosk`
- **THEN** el kiosk se sirve para la empresa con `slug = "acme"` sin ningún parámetro de empresa en el path

#### Scenario: Kiosk en dominio apex responde 404
- **WHEN** llega una petición a `GET mango-app.test/kiosk` (sin subdominio de tenant)
- **THEN** la respuesta es 404
