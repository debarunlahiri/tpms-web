# TPMS - Total Project Management System

An enterprise-level CRM web application built with PHP, MySQL, HTML, Tailwind CSS, and animations.

## Features

- **User Authentication** with customizable role-based access
- **Fully Customizable Roles** - Admin can create Client, Employee, Freelancer, or any custom role with specific permissions
- **Dashboard** with analytics, KPIs, sales pipeline, recent activity, and pending tasks
- **Contacts Management** - customers and prospects with client user linking
- **Leads Management** - track leads through the sales funnel
- **Deals Pipeline** - Kanban-style drag-and-drop pipeline
- **Tasks & Activities** - calls, emails, meetings, follow-ups
- **Reports & Analytics** - revenue trends, conversion rates, top performers
- **Settings** - system configuration, user management, and role/permission management
- **Responsive Design** with Tailwind CSS
- **Smooth Animations** for enterprise-grade UX
- **Custom Dialog Modals** replacing native browser alerts/confirms

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher / MariaDB
- Apache/Nginx web server
- Composer (optional, for future dependency management)

## Installation

1. **Clone or extract** the project into your web server directory (e.g., `htdocs/crm-web`)

2. **Create the database** by visiting:
   ```
   http://localhost/crm-web/setup.php
   ```
   This will create the `tpms` database and default admin user.

3. **Configure environment variables**:
   - Copy `.env.example` to `.env`
   - Update `.env` with your database credentials:
   ```env
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=tpms
   APP_NAME=TPMS
   APP_URL=http://localhost/crm-web
   ```

4. **Login** with default credentials:
   - Email: `admin@crm.com`
   - Password: `admin123`

5. **Change the default password** after first login via Settings > Users.

## Default User Roles

| Role | Access |
|------|--------|
| Admin | Full access to all modules, settings, users, and roles |
| Manager | Access to dashboard, leads, contacts, deals, tasks, reports, and users |
| Sales Rep | Access to dashboard, leads, contacts, deals, and tasks |
| Employee | Access to dashboard, contacts, and tasks |
| Freelancer | Access to dashboard and tasks |
| Client | Limited access to own linked records and dashboard |

Admins can create custom roles with any combination of permissions via **Settings > Roles & Permissions**.

## Project Structure

```
crm-web/
├── index.php              # Login page
├── dashboard.php          # Main dashboard
├── projects.php           # Project tracker with Kanban board
├── leads.php              # Leads management
├── contacts.php           # Contacts management
├── deals.php              # Deals pipeline
├── tasks.php              # Tasks and activities
├── media.php              # Media gallery (images/videos)
├── reports.php            # Reports and analytics
├── logs.php               # Activity logs viewer
├── storage.php            # Server storage management
├── settings.php           # System settings and user/role management
├── setup.php              # Database initialization
├── .env                   # Environment configuration
├── .gitignore             # Git ignore rules
├── .htaccess              # Security and server config
├── config/
│   └── database.php       # Database configuration
├── includes/
│   ├── header.php         # Page header template
│   ├── footer.php         # Page footer template
│   ├── sidebar.php        # Navigation sidebar
│   ├── topbar.php         # Top navigation bar
│   ├── auth.php           # Authentication handler
│   └── functions.php      # Helper functions
├── assets/
│   ├── css/style.css      # Custom styles
│   └── js/app.js          # Application JavaScript
├── api/
│   ├── deal_actions.php   # Deal API endpoints
│   └── upload.php         # File upload handler
├── uploads/               # Uploaded media files
└── database/
    └── crm.sql            # Database schema
```

## Security Notes

- Change default admin credentials immediately after setup
- Keep `.env` secure and do not commit it to public repositories
- CSRF protection is enabled on all forms
- Passwords are hashed using bcrypt
- Session security: httponly, secure, samesite, strict mode, periodic regeneration
- Login rate limiting (5 attempts per 15 minutes per IP)
- Upload rate limiting and file type validation
- Input sanitization and prepared statements prevent XSS/SQL injection
- `.htaccess` security headers and file access restrictions
- **Note:** For production DDoS protection, use a CDN/WAF (Cloudflare, AWS Shield) or server-level firewall

## License

Proprietary - TPMS Enterprise Solution
