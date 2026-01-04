Param(
    [string]$BaseUrl = "http://localhost/CultureConnect(php)"
)

Write-Host "Running quick smoke tests against: $BaseUrl" -ForegroundColor Cyan

$pages = @(
    '/',
    '/explore.php',
    '/index.php',
    '/login.php',
    '/register.php',
    '/create_post.php',
    '/post.php',
    '/profile.php'
)

foreach ($p in $pages) {
    $url = ($BaseUrl.TrimEnd('/') + $p)
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
        $status = $resp.StatusCode
        $len = ($resp.Content | Measure-Object -Character).Characters
        if ($status -eq 200 -and $len -gt 50) {
            Write-Host "OK    $url (200, $len chars)" -ForegroundColor Green
        } else {
            Write-Host "WARN  $url (status=$status, len=$len)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "ERROR $url -> $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "Smoke test complete." -ForegroundColor Cyan
