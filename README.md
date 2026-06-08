<div align="center">

<img src="public/img/logo.png" alt="Run & Eat Logo" width="120" />

# Run &amp; Eat

**Urban Gastronomic Events Platform**

[![PHP](https://img.shields.io/badge/PHP-8.0+-FFA208?style=flat-square&labelColor=0A192F&color=FFA208)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-FFA208?style=flat-square&labelColor=0A192F&color=FFA208)](https://www.mysql.com/)
[![HTML](https://img.shields.io/badge/HTML5-CSS3-FFA208?style=flat-square&labelColor=0A192F&color=FFA208)](#)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-FFA208?style=flat-square&labelColor=0A192F&color=FFA208)](#)
[![License](https://img.shields.io/badge/Academic-Project-64FFDA?style=flat-square&labelColor=0A192F&color=64FFDA)](#)
[![Version](https://img.shields.io/badge/version-1.0.0-64FFDA?style=flat-square&labelColor=0A192F&color=64FFDA)](#)


---


</div>

## Table of Contents

- [Description](#-description)
- [Tech Stack](#-tech-stack)
- [Features](#-features)
- [User Roles](#-user-roles)

---

## Description

**Run & Eat** is a web platform specialized in publishing and managing urban gastronomic events. It connects culinary experience organizers with participants looking to discover local gastronomy in an active and social way: fun runs, wine tastings, burger runs, tapas routes, and much more.

The project was developed entirely as an academic project for the **Web Application Development (DAW)** course at Stucom, Barcelona, using standard web technologies without external frameworks.

| Feature | Detail |
|---|---|
| Type | Event web platform |
| Main City | Barcelona, Spain |
| Roles | Client · Organizer · Admin |
| Authentication | Native PHP Sessions |
| No external dependencies | Native PHP + MySQL |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.0+ (native, no frameworks) |
| Database | MySQL 8.0 — PDO with prepared statements |
| Frontend | HTML5 + CSS3 + JavaScript ES6 |
| Server | Apache (XAMPP recommended for local) |
| Version Control | Git |
| File Uploads | PHP `finfo` + `move_uploaded_file()` |

---


## Features

### Authentication

| Action | Description | File |
|---|---|---|
| Register | Field validation, email format, password matching, user type, and DB duplicates | `UserController.php` |
| Login | Credential check against DB and session writing | `UserController.php` |
| Logout | Full session destruction with `session_unset()` + `session_destroy()` | `UserController.php` |
| Guard | Middleware that protects routes by role; redirects to forbidden if not authorized | `auth_guard.php` |

### User Profile

| Action | Description |
|---|---|
| Edit info | Update name and email with validation for duplicated email in another account |
| Change password | Current password verification before applying changes; minimum 8 characters |
| Profile photo | Upload with real MIME validation (`finfo`), allowed extension, and 2 MB limit |
| My events | List of registered events using `JOIN` with the `INSCRIPCIONES` table |

### Events

| Action | Role | Description |
|---|---|---|
| List events | All | Main page with pagination and city search |
| View detail | All | Full info, integrated Google Maps map, registration button |
| Create event | Organizer | Form with image, level, cuisine type, price, capacity, and distance |
| Register | Client | Full transaction flow: validates availability, prevents duplicates, decrements spots; redirects to login if unauthenticated |

### User Interface

The platform prioritizes a smooth and interactive user experience. **Both events and organizers are displayed using a dynamic carousel managed by the jQuery Slick plugin**. This implementation significantly improves navigation, allowing touch sliding on mobile and visual controls on desktop, optimizing the visual presentation of content.

#### Color Palette
The visual identity of **Run & Eat** uses an elegant dark mode with vibrant accents:

- 🟠 `#FFA208` — **Vibrant Orange**: Main accent color (buttons, links, stars, icons).
- 🌑 `#0A192F` — **Deep Navy Blue**: Main background color.
- 🌒 `#112240` — **Light Navy Blue**: Background color for cards, headers, and menus.
- ⚪ `#FFFFFF` — **White**: Main text.
- 🔘 `#233554` — **Grayish Blue**: Borders, dividers, and secondary elements.

---

## Sequence Diagrams

Below are the main interaction flows of the application to better understand the communication between the client, the server, and the database.

### 1. Event Management

#### View Event Detail (Read)
```mermaid
sequenceDiagram
    actor User
    participant Frontend
    participant Backend (PHP)
    participant Database

    User->>Frontend: Click on an event
    Frontend->>Backend (PHP): GET /evento.php?id=X
    Backend (PHP)->>Database: SELECT * FROM EVENTOS WHERE id = X
    Database-->>Backend (PHP): Returns event data
    Backend (PHP)->>Frontend: Renders view with data
    Frontend-->>User: Displays full detail and map
```

#### Create Event
```mermaid
sequenceDiagram
    actor Organizer
    participant Frontend
    participant Backend (PHP)
    participant Database

    Organizer->>Frontend: Fills new event form
    Frontend->>Backend (PHP): POST /crear-evento.php
    Backend (PHP)->>Backend (PHP): Validates session and role (organizer)
    Backend (PHP)->>Backend (PHP): Validates data and image
    Backend (PHP)->>Database: INSERT INTO EVENTOS
    Database-->>Backend (PHP): Insertion confirmation
    Backend (PHP)-->>Frontend: Redirects with success
    Frontend-->>Organizer: Displays published event
```

#### Modify Event
```mermaid
sequenceDiagram
    actor Organizer
    participant Frontend
    participant Backend (PHP)
    participant Database

    Organizer->>Frontend: Edits event form
    Frontend->>Backend (PHP): POST /editar-evento.php (id_evento)
    Backend (PHP)->>Backend (PHP): Validates session and event ownership
    Backend (PHP)->>Database: UPDATE EVENTOS
    Database-->>Backend (PHP): Update confirmation
    Backend (PHP)-->>Frontend: Redirects to event detail
    Frontend-->>Organizer: Displays updated event
```

#### Delete Event
```mermaid
sequenceDiagram
    actor Organizer
    participant Frontend
    participant Backend (PHP)
    participant Database

    Organizer->>Frontend: Clicks on 'Delete Event'
    Frontend->>Backend (PHP): POST /eliminar-evento.php (id_evento)
    Backend (PHP)->>Backend (PHP): Validates session and event ownership
    Backend (PHP)->>Database: UPDATE EVENTOS SET activo = 0 WHERE id = id_evento
    Database-->>Backend (PHP): Soft-delete confirmation
    Backend (PHP)-->>Frontend: Redirects to event listing
    Frontend-->>Organizer: Updates view without the event
```

### 2. User Management

#### Modify Profile
```mermaid
sequenceDiagram
    actor User
    participant Frontend
    participant Backend (PHP)
    participant Database

    User->>Frontend: Sends new data (name, email, photo)
    Frontend->>Backend (PHP): POST /perfil.php
    Backend (PHP)->>Backend (PHP): Validates session and data
    Backend (PHP)->>Database: Checks if email already exists in another account
    Database-->>Backend (PHP): Validation result
    Backend (PHP)->>Database: UPDATE USUARIOS
    Database-->>Backend (PHP): Confirmation
    Backend (PHP)-->>Frontend: Success message
    Frontend-->>User: Displays updated profile
```

#### Change Password
```mermaid
sequenceDiagram
    actor User
    participant Frontend
    participant Backend (PHP)
    participant Database

    User->>Frontend: Sends current and new password
    Frontend->>Backend (PHP): POST /cambiar-password.php
    Backend (PHP)->>Database: SELECT contrasena FROM USUARIOS
    Database-->>Backend (PHP): Password hash
    Backend (PHP)->>Backend (PHP): password_verify(current, hash)
    Backend (PHP)->>Backend (PHP): password_hash(new)
    Backend (PHP)->>Database: UPDATE USUARIOS SET contrasena = new_hash
    Database-->>Backend (PHP): Confirmation
    Backend (PHP)-->>Frontend: Success message
    Frontend-->>User: Confirms change
```

#### Delete User
```mermaid
sequenceDiagram
    actor User
    participant Frontend
    participant Backend (PHP)
    participant Database

    User->>Frontend: Requests account deletion
    Frontend->>Backend (PHP): POST /eliminar-cuenta.php
    Backend (PHP)->>Backend (PHP): Validates session
    Backend (PHP)->>Database: DELETE FROM USUARIOS WHERE id = session_id
    Database-->>Backend (PHP): Deletion confirmation
    Backend (PHP)->>Backend (PHP): session_destroy()
    Backend (PHP)-->>Frontend: Redirects to index.php
    Frontend-->>User: Displays public home page
```

---

## User Roles

Access to different sections is managed through `auth_guard.php` using three main functions:

```php
auth_require_login();           // Requires active session
auth_require_role("organizador"); // Requires specific role
auth_is("organizador");         // Returns bool — conditional use in views
auth_check();                   // Checks if there is an active session
```

| Permission | `client` | `organizer` | `admin` |
|---|---|---|---|
| View event listing | ✅ | ✅ | ✅ |
| View event detail | ✅ | ✅ | ✅ |
| Register for event | ✅ | ✅ | ✅ |
| Manage profile | ✅ | ✅ | ✅ |
| Create events | ❌ | ✅ | ✅ |
| "Create event" button in nav | ❌ | ✅ | ✅ |
| Edit / Delete any event | ❌ | ✅\* | ✅ |

If a user with a `client` role tries to access a route protected by `organizer`, `auth_guard.php` renders a **restricted access** page instead of redirecting, showing the user's name and a link to the contact form.

---

### Tables

| Table | Description | Key Fields |
|---|---|---|
| `USUARIOS` | Registry of all users | `id_usuario`, `nombre_completo`, `email`, `contrasena`, `tipo_usuario`, `foto_perfil`, `fecha_registro`, `activo` |
| `EVENTOS` | Events published by organizers | `id_evento`, `id_organizador`, `titulo`, `descripcion`, `fecha`, `hora`, `ciudad`, `direccion`, `precio`, `participantes`, `nivel`, `tipo` |
| `INSCRIPCIONES` | N:M relationship between users and events | `id_usuario`, `id_evento` |
| `VALORACIONES` | User reviews on attended events | `id_valoracion`, `id_usuario`, `id_evento`, `puntuacion`, `comentario` |
| `FAVORITOS` | Events saved by users | `id_usuario`, `id_evento` |

---

</div>

<br><br>
<hr>
