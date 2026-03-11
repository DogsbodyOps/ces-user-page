# CES User Page

A PHP web portal that lets authorised staff create Active Directory user accounts for multiple customers.  The portal collects the required information, calls a PowerShell script to create the account, add it to the correct groups and generate a secure temporary password, then displays the new **SAMAccountName** and password on screen.

---

## Features

| Feature | Detail |
|---|---|
| Customer selection | Dropdown mapped to customer-specific OUs and AD groups |
| Auto SAMAccountName | First initial + surname, lowercase, de-duplicated (`jsmith`, `jsmith2`, …) |
| Secure password | 16-character random password (≤ 20 chars) with upper, lower, digit and special characters |
| Change at next logon | All accounts require a password reset on first login |
| CSRF protection | Token validated on every form submission |
| Input validation | Server-side regex validation of all fields; sanitisation of free-text inputs |
| Injection-safe | User data passed to PowerShell via a temp file — nothing user-supplied ever appears on the command line |

---

## Requirements

| Component | Minimum version |
|---|---|
| PHP | 8.1 |
| PowerShell | 5.1 or PowerShell 7+ |
| RSAT / ActiveDirectory module | Installed on the web server or a remote host |
| Windows Server | Any version that supports `New-ADUser` |

The web server process (e.g. `IIS_IUSRS`) must have permission to **execute PowerShell** and **write to `%TEMP%`**.

---

## Installation

### 1 — Copy files to the web root

Place all files under a directory served by IIS (or any PHP-capable web server on Windows):

```
C:\inetpub\wwwroot\ces-user-page\
```

### 2 — Configure customers

Edit `config\customers.php` and replace the example entries with your real customers:

```php
'acme' => [
    'name'   => 'Acme Ltd',
    'ou'     => 'OU=Users,OU=Acme,DC=corp,DC=local',
    'groups' => ['Acme-Users', 'Acme-Email'],
],
```

### 3 — Set PowerShell execution policy

The IIS application pool account must be able to run the script:

```powershell
Set-ExecutionPolicy -Scope LocalMachine -ExecutionPolicy RemoteSigned
```

Or restrict it to the script path via a GPO / IIS `web.config` setting.

### 4 — Test

Browse to `https://<your-server>/ces-user-page/` and create a user.

---

## File structure

```
ces-user-page/
├── index.php                   # User creation form
├── process.php                 # Form handler → calls PowerShell → shows result
├── config/
│   └── customers.php           # Customer → OU + groups mapping
├── scripts/
│   └── create_ad_user.ps1      # PowerShell: creates AD user, adds groups, returns JSON
└── css/
    └── style.css               # Portal stylesheet
```

---

## Security notes

* All user input is validated server-side before being passed to PowerShell.
* User data travels to the PowerShell script via a temporary file (not the command line), preventing shell injection.
* Passwords are generated inside the PowerShell script using `Get-Random` over a curated character set and are never stored anywhere.
* The portal enforces HTTPS in production via standard IIS/web server configuration (configure TLS on the web server separately).
* Access control is handled at the network/web-server level — restrict which hosts can reach the portal via firewall rules, IIS IP restrictions, or VPN access.
