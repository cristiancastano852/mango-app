## ADDED Requirements

### Requirement: Landing page pública accesible sin autenticación
El sistema SHALL servir una landing page en la ruta `/` accesible sin auth ni tenant middleware. La página SHALL incluir: sección hero con tagline y CTA, sección de features clave, sección de pricing con 4 planes, y footer con links básicos.

#### Scenario: Visitante accede a la landing page
- **WHEN** cualquier visitante (sin sesión) accede a `GET /`
- **THEN** la respuesta es 200 con la landing page renderizada
- **THEN** la página incluye un botón CTA que enlaza a `/register/company`

#### Scenario: Usuario autenticado accede a la landing
- **WHEN** un usuario con sesión activa accede a `GET /`
- **THEN** la landing se muestra igualmente (no redirige automáticamente)

---

### Requirement: Sección de pricing informativa con 4 planes
La landing SHALL mostrar los 4 planes del SaaS: Free (hasta 5 empleados), Básico ($15/mes, hasta 25), Pro ($35/mes, hasta 100), Enterprise ($75/mes, ilimitado). Cada plan SHALL mostrar precio, límite de empleados y un botón CTA.

#### Scenario: Pricing visible en la landing
- **WHEN** visitante accede a `GET /`
- **THEN** la página muestra 4 cards de pricing con nombre, precio y límite de empleados de cada plan
- **THEN** cada card tiene un botón que enlaza a `/register/company`

#### Scenario: Acceso directo a pricing
- **WHEN** visitante accede a `GET /pricing`
- **THEN** la respuesta es 200 con la misma sección de pricing (o redirige a `/#pricing`)

---

### Requirement: Rate limiting en rutas públicas
Las rutas públicas SHALL tener rate limiting de 60 requests/minuto por IP para prevenir abuso.

#### Scenario: Rate limit excedido
- **WHEN** una IP realiza más de 60 requests en un minuto a rutas públicas
- **THEN** la respuesta es 429 Too Many Requests
