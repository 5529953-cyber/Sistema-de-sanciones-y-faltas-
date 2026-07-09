# SISFAL — Sistema de Control de Sanciones y Faltas

## Estructura del proyecto

```
SISFAL/
├── index.html                    ← Punto de entrada (SPA)
├── assets/
│   ├── css/
│   │   └── main.css              ← Estilos completos
│   └── js/
│       └── app.js                ← Lógica SPA + llamadas API
├── app/
│   └── controllers/
│       ├── AuthController.php    ← Login / logout / sesión
│       ├── EstudiantesController.php
│       ├── FaltasController.php
│       ├── SancionesController.php
│       └── DashboardController.php
└── config/
    ├── database.php              ← ⚠️ CONFIGURA AQUÍ TU BD REMOTA
    └── schema.sql                ← Script para crear las tablas
```

---

## Pasos para instalar

### 1. Configurar la base de datos remota

Edita `config/database.php` y cambia:

```php
define('DB_HOST',  'TU_HOST_REMOTO');  // IP o dominio del servidor MySQL
define('DB_NAME',  'sisfal_db');
define('DB_USER',  'TU_USUARIO');
define('DB_PASS',  'TU_CONTRASEÑA');
```

### 2. Crear las tablas

Ejecuta el archivo `config/schema.sql` en tu servidor MySQL:

```bash
mysql -h TU_HOST -u TU_USUARIO -p < config/schema.sql
```

O cópialo y pégalo en phpMyAdmin.

### 3. Subir al servidor

Sube toda la carpeta `SISFAL/` a tu servidor web (Apache/Nginx con PHP 8+).

Ejemplo en cPanel: sube a `public_html/SISFAL/`

### 4. Configurar la URL de la API

En `assets/js/app.js`, línea ~12, cambia:

```js
const BASE_API = './app/controllers';
// o si es un subdominio:
const BASE_API = 'https://tudominio.com/SISFAL/app/controllers';
```

### 5. Acceder

Abre en el navegador: `https://tudominio.com/SISFAL/`

**Credenciales por defecto:**
- Usuario: `admin`
- Contraseña: `password`  ← ¡Cámbiala inmediatamente!

---

## Requisitos

- PHP 8.0+ con extensión PDO y pdo_mysql
- MySQL 5.7+ / MariaDB 10.3+
- Servidor web: Apache o Nginx
- El servidor MySQL remoto debe permitir conexiones desde la IP de tu servidor web

---

## Tecnologías

- **Frontend:** HTML5 + CSS3 + JavaScript vanilla (SPA)
- **Backend:** PHP 8 con patrón MVC
- **Base de datos:** MySQL via PDO (prepared statements)
- **Autenticación:** PHP Sessions + bcrypt

---

## Seguridad incluida

- Contraseñas hasheadas con `password_hash()` (bcrypt)
- Todas las consultas con Prepared Statements (anti SQL injection)
- Verificación de sesión en cada controlador
- CORS configurable en el servidor
