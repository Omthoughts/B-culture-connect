<#
    scripts/setup_db.ps1

    Usage:
      Open PowerShell as Administrator and run:
        .\scripts\setup_db.ps1

    The script will prompt for MySQL root credentials and whether to create a dedicated DB user.
    It will:
      - create database `cultureconnect` if missing
      - optionally create a DB user and grant privileges
      - import the migration SQL
      - update the project's `.env` with the chosen credentials
      - run the PHP seeder to create a test user/post

    Note: This script calls the `mysql` CLI and `php` on your PATH. Ensure XAMPP's MySQL and PHP are accessible.
#>

Set-StrictMode -Version Latest
Clear-Host

Write-Host "CultureConnect DB setup script" -ForegroundColor Cyan

$projectRoot = Split-Path -Parent $PSScriptRoot
$migration = Join-Path $projectRoot 'database\migrations\20260102_create_schema.sql'
$envFile = Join-Path $projectRoot '.env'

if (-Not (Test-Path $migration)) {
    Write-Error "Migration file not found: $migration"
    exit 1
}

# Get MySQL root credentials
$rootUser = Read-Host "MySQL root user (default 'root')" -Prompt 'Root user' -Default 'root'
$rootPassSecure = Read-Host "Root password (press Enter if empty)" -AsSecureString
[void][Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPassSecure)) | Out-Null
$rootPass = ''
if ($rootPassSecure.Length -gt 0) {
    $rootPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPassSecure)
    $rootPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto($rootPtr)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($rootPtr) | Out-Null
}

# Ask to create dedicated user
$createUser = Read-Host "Create dedicated DB user? (y/N)"
$dbUser = 'root'
$dbPass = $rootPass
if ($createUser -match '^[Yy]') {
    $dbUser = Read-Host "New DB username (default: cc_user)" -Prompt 'DB user' -Default 'cc_user'
    $dbPassPlain = Read-Host "Password for $dbUser (leave empty to auto-generate)"
    if ([string]::IsNullOrWhiteSpace($dbPassPlain)) {
        # generate a random password
        Add-Type -AssemblyName System.Web
        $dbPassPlain = [System.Web.Security.Membership]::GeneratePassword(16,2)
        Write-Host "Generated password for $dbUser: $dbPassPlain" -ForegroundColor Yellow
    }
    $dbPass = $dbPassPlain
    # create user and grant privileges
    $createUserSql = "CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass'; GRANT ALL PRIVILEGES ON cultureconnect.* TO '$dbUser'@'localhost'; FLUSH PRIVILEGES;"
    Write-Host "Creating DB user and granting privileges..."
    # Use a temporary defaults-extra-file to avoid exposing the password on the command line
    $optFile = [IO.Path]::GetTempFileName()
    $optContent = "[client]`nuser=$rootUser"
    if ($rootPass -ne '') { $optContent += "`npassword=$rootPass" }
    Set-Content -Path $optFile -Value $optContent -Encoding ASCII
    try {
        & mysql --defaults-extra-file="$optFile" -e $createUserSql
    } finally {
        Remove-Item -Path $optFile -ErrorAction SilentlyContinue
    }
}

# Create DB
Write-Host "Creating database cultureconnect if it doesn't exist..."
# Use temporary option file for secure invocation
$optFile = [IO.Path]::GetTempFileName()
$optContent = "[client]`nuser=$rootUser"
if ($rootPass -ne '') { $optContent += "`npassword=$rootPass" }
Set-Content -Path $optFile -Value $optContent -Encoding ASCII
try {
    & mysql --defaults-extra-file="$optFile" -e "CREATE DATABASE IF NOT EXISTS cultureconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
} finally {
    Remove-Item -Path $optFile -ErrorAction SilentlyContinue
}

# Import migration
Write-Host "Importing migration $migration"
# Use temporary option file for import to avoid password on the command line
$optFile = [IO.Path]::GetTempFileName()
$optContent = "[client]`nuser=$rootUser"
if ($rootPass -ne '') { $optContent += "`npassword=$rootPass" }
Set-Content -Path $optFile -Value $optContent -Encoding ASCII
try {
    # Use call operator and native redirection to import
    & mysql --defaults-extra-file="$optFile" cultureconnect < "$migration"
} finally {
    Remove-Item -Path $optFile -ErrorAction SilentlyContinue
}

# Update .env
Write-Host "Updating .env with DB credentials..."
$envLines = @()
$envLines += "DB_HOST=127.0.0.1"
$envLines += "DB_NAME=cultureconnect"
$envLines += "DB_USER=$dbUser"
$envLines += "DB_PASS=$dbPass"
$envLines += "DB_PORT=3306"

Set-Content -Path $envFile -Value $envLines -Encoding UTF8
Write-Host "Wrote $envFile"

# Run seeder
Write-Host "Running seeder..."
$phpCmd = "php \"$projectRoot\scripts\seed.php\""
Write-Host "Running: $phpCmd"
iex $phpCmd

Write-Host "Setup complete. You can now open the site in your browser." -ForegroundColor Green
