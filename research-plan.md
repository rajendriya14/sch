# Research Plan: Workforce Automation Scheduling System
## Best Practices — PHP Native + MySQL MVC + Bootstrap 5

---

## 1. ARSITEKTUR FOLDER (MVC Native)

```
scheduling-system/
├── config/
│   ├── database.php          # PDO Singleton + auto-migration
│   ├── autoload.php          # PSR-4-like autoloader
│   └── app.php               # App constants (BASE_URL, APP_NAME, ds)
├── controllers/
│   ├── DashboardController.php
│   ├── ShiftController.php
│   ├── StaffController.php
│   ├── ForecastController.php
│   ├── LeaveController.php
│   ├── TlScheduleController.php
│   └── ScheduleController.php
├── models/
│   ├── Shift.php
│   ├── Employee.php
│   ├── ForecastRequirement.php
│   ├── LeaveRequest.php
│   ├── TlSchedule.php
│   ├── ScheduleHeader.php
│   └── ScheduleDetail.php
├── services/
│   ├── SchedulingEngine.php    # Core algorithm (7 rules)
│   ├── ScheduleValidator.php   # Post-generation validation
│   ├── RuleInterface.php       # Strategy pattern interface
│   ├── StaffService.php
│   ├── ShiftService.php
│   ├── ForecastService.php
│   ├── LeaveService.php
│   └── TlScheduleService.php
├── helpers/
│   ├── FormHelper.php          # Form builders
│   └── TimeHelper.php          # Week/Month helpers
├── views/
│   ├── layouts/
│   │   ├── header.php
│   │   └── footer.php
│   ├── dashboard/
│   │   └── index.php
│   ├── shifts/
│   │   ├── list.php
│   │   └── form.php
│   ├── staff/
│   │   ├── list.php
│   │   └── form.php
│   ├── forecast/
│   │   └── list.php
│   ├── leave/
│   │   ├── list.php
│   │   └── form.php
│   ├── tl_schedule/
│   │   ├── list.php
│   │   ├── form.php
│   │   └── input.php
│   └── schedule/
│       ├── generate.php
│       ├── view.php
│       └── validation.php
├── database/
│   ├── schema.sql
│   └── seed.sql
└── public/
    ├── index.php               # Front controller (ENTRY POINT)
    ├── .htaccess               # RewriteRule ke index.php
    ├── assets/
    │   ├── css/
    │   │   └── style.css
    │   └── js/
    │       └── app.js
```

**Key Principle**: `public/` is the **document root**. Semua request masuk lewat `public/index.php`. Classes di-load via autoloader dari folder `config/`, `controllers/`, `models/`, `services/`, `helpers/`.

---

## 2. DATABASE DESIGN (8 Tables)

### 2.1 `shifts`
```sql
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_code VARCHAR(10) NOT NULL UNIQUE,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    category ENUM('Morning','Swing','Night','Leave') NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
**Seed (16 shifts)**: S1(06:00-14:00,M), S2(07:00-15:00,M), S3(08:00-16:00,M), S4(09:00-17:00,M), S5(10:00-18:00,M), S6(10:30-18:30,M), S7(11:00-19:00,M), M1(14:00-22:00,Swing), M2(15:00-23:00,Swing), M3(16:00-00:00,Swing), M4(17:00-01:00,Swing), M5(22:00-06:00,Night), M6(23:00-07:00,Night), M7(00:00-08:00,Night), OFF(00:00-00:00,Leave).

### 2.2 `employees`
```sql
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    gender ENUM('Male','Female') NOT NULL,
    team_leader VARCHAR(100) DEFAULT NULL,
    employment_status ENUM('Active','Inactive') DEFAULT 'Active',
    join_date DATE DEFAULT NULL,
    compo VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2.3 `forecast_requirements`
```sql
CREATE TABLE forecast_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun,1=Mon..6=Sat',
    hour TIME NOT NULL,
    required_count INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_day_hour (day_of_week, hour)
);
```
24 rows per day × 7 days = 168 rows.

### 2.4 `leave_requests`
```sql
CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL,
    leave_date DATE NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);
```

### 2.5 `tl_schedules`
```sql
CREATE TABLE tl_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_leader VARCHAR(100) NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun..6=Sat',
    shift_code VARCHAR(10) NOT NULL,
    week_start_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_code) REFERENCES shifts(shift_code)
);
```

### 2.6 `female_restricted_shifts`
```sql
CREATE TABLE female_restricted_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_code VARCHAR(10) NOT NULL UNIQUE,
    FOREIGN KEY (shift_code) REFERENCES shifts(shift_code)
);
```
**Seed**: S5, S7, M1, M3, M4, M5, M6.

### 2.7 `schedule_headers`
```sql
CREATE TABLE schedule_headers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_start_date DATE NOT NULL,
    status ENUM('Draft','Published') DEFAULT 'Draft',
    generated_by VARCHAR(100) DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_week (week_start_date)
);
```

### 2.8 `schedule_details`
```sql
CREATE TABLE schedule_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_header_id INT NOT NULL,
    employee_id VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    shift_code VARCHAR(10) NOT NULL,
    FOREIGN KEY (schedule_header_id) REFERENCES schedule_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (shift_code) REFERENCES shifts(shift_code),
    UNIQUE KEY uq_emp_date (employee_id, date)
);
```

---

## 3. PDO CONNECTION PATTERN (Singleton + Auto-Migration)

### Best Practice: Database class dengan:
1. **Singleton** — satu koneksi PDO untuk seluruh request
2. **Auto-migration** — `migrate()` dipanggil di `index.php` setelah koneksi
3. **Prepared Statements** — semua query via PDO parameter binding
4. **Error mode** — `PDO::ERRMODE_EXCEPTION` untuk development

```php
class Database {
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = 'localhost';
            $dbname = 'schedule_db';
            $user = 'root';
            $pass = '';
            
            self::$instance = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        return self::$instance;
    }
    
    public static function migrate(): void {
        // CREATE TABLES IF NOT EXISTS...
        // SEED DATA IF TABLES WERE JUST CREATED...
    }
}
```

---

## 4. ROUTING (Front Controller Pattern)

### Best Practice: Single entry point `public/index.php`

```php
<?php
session_start();
require_once __DIR__ . '/../config/autoload.php';
use Config\Database;

// Auto-migration
Database::getInstance();
Database::migrate();

// Simple routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

$controllerMap = [
    'dashboard'     => 'Controllers\\DashboardController',
    'shifts'        => 'Controllers\\ShiftController',
    'staff'         => 'Controllers\\StaffController',
    'forecast'      => 'Controllers\\ForecastController',
    'leave'         => 'Controllers\\LeaveController',
    'tl_schedule'   => 'Controllers\\TlScheduleController',
    'schedule'      => 'Controllers\\ScheduleController',
];

$controllerClass = $controllerMap[$page] ?? null;
if (!$controllerClass) {
    die('Page not found');
}

$controller = new $controllerClass();
$pageName = $page;
$pageTitle = ucwords(str_replace('_', ' ', $page));

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    die('Action not found');
}
```

### .htaccess (for Apache):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## 5. AUTOLOADER (PSR-4-like)

```php
<?php
spl_autoload_register(function ($class) {
    $prefixes = [
        'Config\\'      => __DIR__ . '/../config/',
        'Controllers\\' => __DIR__ . '/../controllers/',
        'Models\\'      => __DIR__ . '/../models/',
        'Services\\'    => __DIR__ . '/../services/',
        'Helpers\\'     => __DIR__ . '/../helpers/',
    ];
    
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
```

**Namespace mapping**:
- `Config\Database` → `config/Database.php`
- `Controllers\ShiftController` → `controllers/ShiftController.php`
- `Models\Shift` → `models/Shift.php`
- `Services\SchedulingEngine` → `services/SchedulingEngine.php`
- `Helpers\FormHelper` → `helpers/FormHelper.php`

---

## 6. CONTROLLER PATTERN

```php
<?php
namespace Controllers;

class ShiftController {
    protected $viewPath = __DIR__ . '/../views/';
    
    public function index() {
        global $pageName;
        $shifts = \Models\Shift::getAll();
        include $this->viewPath . 'shifts/list.php';
    }
    
    public function create() {
        global $pageName;
        include $this->viewPath . 'shifts/form.php';
    }
    
    public function store() {
        $data = $_POST;
        // validation & save
        header('Location: ?page=shifts');
        exit;
    }
}
```

---

## 7. MODEL PATTERN (Static PDO Queries)

```php
<?php
namespace Models;

use Config\Database;

class Shift {
    public static function getAll(): array {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM shifts ORDER BY FIELD(category,'Morning','Swing','Night','Leave'), start_time");
        return $stmt->fetchAll();
    }
    
    public static function find($id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public static function create(array $data): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO shifts (shift_code, start_time, end_time, category, description) VALUES (?,?,?,?,?)");
        return $stmt->execute([$data['shift_code'], $data['start_time'], $data['end_time'], $data['category'], $data['description'] ?? null]);
    }
    
    public static function update($id, array $data): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE shifts SET shift_code=?, start_time=?, end_time=?, category=?, description=? WHERE id=?");
        return $stmt->execute([$data['shift_code'], $data['start_time'], $data['end_time'], $data['category'], $data['description'] ?? null, $id]);
    }
    
    public static function delete($id): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM shifts WHERE id=?");
        return $stmt->execute([$id]);
    }
}
```

---

## 8. SCHEDULING ENGINE (7 Business Rules)

### Greedy Heuristic Algorithm Steps:

```
STEP 1: Assign "OFF" for employees with leave requests → ScheduleDetail
STEP 2: Assign weekly day-off (every employee gets 1 OFF per week)
STEP 3: Assign consecutive days pattern (check TL schedule preference)
STEP 4: Assign shifts based on Forecast Requirements (hourly gaps)
STEP 5: Female Restriction Check — reassign females from restricted shifts
STEP 6: M3 Rule — max 2 consecutive M3 shifts per employee
STEP 7: TL Meeting — all TLs get same shift on meeting day (e.g., Wednesday M1)
```

```php
<?php
namespace Services;

use Config\Database;

class SchedulingEngine {
    private array $employees;
    private array $shifts;
    private array $forecast;
    private array $leaveRequests;
    private array $tlSchedules;
    
    public function __construct() {
        $this->shifts = \Models\Shift::getActive();
        $this->employees = \Models\Employee::getActive();
    }
    
    public function generate(string $weekStartDate): array {
        $db = Database::getInstance();
        $dates = $this->getWeekDates($weekStartDate);
        
        // STEP 1: Create header
        \Models\ScheduleHeader::create(['week_start_date' => $weekStartDate]);
        $headerId = $db->lastInsertId();
        
        // STEP 2-7: Assignment logic...
        // ...complex heuristic algorithm...
        
        return ['success' => true, 'header_id' => $headerId];
    }
}
```

---

## 9. BOOTSTRAP 5 UI PATTERNS

### Sidebar Navigation (Fixed):
```html
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-white border-end" id="sidebar-wrapper" style="width: 260px; position: fixed; height: 100vh; padding-top: 56px;">
        <nav class="sidebar-nav">
            <a class="nav-link <?= $pageName == 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <!-- ... more links ... -->
        </nav>
    </div>
    <!-- Page Content -->
    <div id="page-content-wrapper" style="margin-left: 260px; padding-top: 56px; width: 100%;">
        <!-- Navbar -->
        <nav class="navbar navbar-dark bg-dark fixed-top" style="margin-left: 260px;">
            <span class="navbar-brand ms-3">Workforce Scheduler</span>
        </nav>
        <div class="container-fluid p-4">
```

### Card Layout:
```html
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> Staff Master</h5>
    </div>
    <div class="card-body">
        <!-- content -->
    </div>
</div>
```

### DataTable:
```html
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" id="dataTable">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- foreach rows -->
        </tbody>
    </table>
</div>
```

---

## 10. CSS CUSTOMIZATION STRATEGY

```css
/* style.css - Overrides Bootstrap defaults */
:root {
    --sidebar-width: 260px;
    --navbar-height: 56px;
}

/* Sidebar link colors - high specificity to beat Bootstrap */
.sidebar-nav a.nav-link,
.sidebar-nav a.nav-link:link,
.sidebar-nav a.nav-link:visited {
    color: #222 !important;
}
.sidebar-nav a.nav-link.active,
.sidebar-nav a.nav-link.active:link,
.sidebar-nav a.nav-link.active:visited {
    color: #fff !important;
    background: #000 !important;
}

/* Grid cell styling for schedule view */
.cell-shift {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    text-align: center;
}
```

**Critical CSS rule**: Always use `!important` for sidebar link colors because Bootstrap's `a:link` has higher specificity than `.nav-link`.

---

## 11. JAVASCRIPT PATTERNS

```javascript
// Week calculation utility
function getMondaysInMonth(year, month) {
    const mondays = [];
    const daysInMonth = new Date(year, month, 0).getDate();
    for (let day = 1; day <= daysInMonth; day++) {
        const d = new Date(year, month - 1, day);
        if (d.getDay() === 1) {
            mondays.push({
                date: `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
                label: `Week ${Math.ceil(day / 7)} (${d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })})`
            });
        }
    }
    return mondays;
}

// Excel paste handler for grids
document.querySelectorAll('[contenteditable]').forEach(grid => {
    grid.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        // Parse TSV and populate grid cells...
    });
});
```

---

## 12. ERROR HANDLING STRATEGY

1. **PHP Exception → Show user-friendly message** (not raw errors)
2. **PDO Exception → Log error, show generic message**
3. **Session management**: `session_start()` ONLY in `index.php` (front controller), never in controllers
4. **CSRF Protection**: Token per form submission
5. **Input validation**: Both client-side (HTML5) and server-side

---

## 13. VALIDATION SUMMARY (Post-Generation)

| Rule | Description | Target |
|------|-------------|--------|
| 1. Leave OFF | Employee with leave = OFF that day | 100% |
| 2. Weekly OFF | Each employee gets 1 OFF per week | 100% |
| 3. Consecutive Days | Same shift 5+ consecutive days? Warning | ≥95% |
| 4. Forecast Coverage | Hourly gaps filled per requirement | ≥90% |
| 5. Female Restriction | No females on restricted shifts | 100% |
| 6. M3 Rule | Max 2 consecutive M3 shifts | 100% |
| 7. TL Meeting | All TLs on same shift meeting day | 100% |

---

## 14. DEVELOPMENT WORKFLOW

1. **Create database** via phpMyAdmin or MySQL CLI
2. **Set `public/` as document root** in Apache/Nginx
3. **PHP built-in server** for local dev:
   ```
   cd scheduling-system/public
   php -S localhost:8000
   ```
4. **Browser**: `http://localhost:8000`
5. **All paths in views**: relative to `public/` (e.g., `assets/css/style.css`)

---

## Summary Checklist

- [ ] Folder structure (config, controllers, models, services, helpers, views, public, database)
- [ ] Database class with singleton PDO + auto-migration
- [ ] PSR-4-like autoloader with namespace mapping
- [ ] Front controller routing (index.php)
- [ ] 8 database tables with proper schema
- [ ] 7 Model classes (static PDO methods)
- [ ] 8 Service classes (business logic)
- [ ] SchedulingEngine with 7-step greedy heuristic
- [ ] ScheduleValidator with 7 rule checks
- [ ] 7 Controller classes
- [ ] Bootstrap 5 views with sidebar layout
- [ ] CSS overrides for sidebar colors
- [ ] JavaScript utilities (week calc, paste handler)
- [ ] .htaccess for clean URLs (optional)
