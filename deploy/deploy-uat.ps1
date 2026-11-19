
$ErrorActionPreference = "Stop"

$image = "manga-laravel:build-$env:BUILD_NUMBER"
$config = "C:\Projects\manga-expense-tracker\deploy\nginx.conf"

if (-not (Test-Path $config)) {
    throw "Nginx configuration not found: $config"
}

$originalConfig = Get-Content $config -Raw

if ($originalConfig.Contains("proxy_pass http://manga-uat-blue:80;")) {
    $active = "blue"
    $target = "green"
    $port = 8082
}
elseif ($originalConfig.Contains("proxy_pass http://manga-uat-green:80;")) {
    $active = "green"
    $target = "blue"
    $port = 8081
}
else {
    throw "Cannot determine active deployment."
}

$activeName = "manga-uat-$active"
$targetName = "manga-uat-$target"
$backupName = "$targetName-backup-$env:BUILD_NUMBER"

$backupCreated = $false
$newCreated = $false
$configChanged = $false

Write-Host "Active: $active"
Write-Host "Deploying: $target"

# Reuse the existing UAT application key.
$envLines = docker inspect $activeName --format '{{range .Config.Env}}{{println .}}{{end}}'

if ($LASTEXITCODE -ne 0) {
    throw "Cannot inspect active container."
}

$keyLine = $envLines | Where-Object {
    $_ -like "APP_KEY=*"
} | Select-Object -First 1

if (-not $keyLine) {
    throw "APP_KEY not found."
}

$uatKey = $keyLine.Substring(8)

try {
    # Preserve the old inactive container for rollback.
    $existing = docker ps -a --filter "name=^/$targetName$" --format "{{.Names}}"

    if ($existing -contains $targetName) {
        docker stop $targetName
        if ($LASTEXITCODE -ne 0) {
            throw "Cannot stop inactive container."
        }

        docker rename $targetName $backupName
        if ($LASTEXITCODE -ne 0) {
            docker start $targetName
            throw "Cannot backup inactive container."
        }

        $backupCreated = $true
    }

    # Start new version without touching the active container.
    docker run -d `
        --name $targetName `
        --network manga-uat-network `
        -p "${port}:80" `
        -v manga-uat-db:/var/www/html/database `
        -e APP_ENV=uat `
        -e APP_DEBUG=false `
        -e APP_KEY="$uatKey" `
        -e DB_CONNECTION=sqlite `
        -e DB_DATABASE=/var/www/html/database/database.sqlite `
        $image

    if ($LASTEXITCODE -ne 0) {
        throw "Cannot start new container."
    }

    $newCreated = $true

    # Health check before switching traffic.
    $healthy = $false

    for ($i = 1; $i -le 20; $i++) {
        curl.exe -fsS --max-time 5 -o NUL "http://localhost:$port/"

        if ($LASTEXITCODE -eq 0) {
            $healthy = $true
            break
        }

        Start-Sleep -Seconds 2
    }

    if (-not $healthy) {
        throw "Health check failed."
    }

    # Switch Nginx to the new version.
    $newConfig = $originalConfig.Replace(
        "proxy_pass http://$activeName`:80;",
        "proxy_pass http://$targetName`:80;"
    )

    if ($newConfig -eq $originalConfig) {
        throw "Nginx upstream replacement failed."
    }

    Set-Content $config $newConfig -Encoding ascii
    $configChanged = $true

    docker exec manga-uat-nginx nginx -t

    if ($LASTEXITCODE -ne 0) {
        throw "Nginx configuration invalid."
    }

    docker exec manga-uat-nginx nginx -s reload

    if ($LASTEXITCODE -ne 0) {
        throw "Nginx reload failed."
    }

    Start-Sleep -Seconds 2

    curl.exe -fsS --max-time 10 -o NUL http://localhost:8083/

    if ($LASTEXITCODE -ne 0) {
        throw "Public UAT health check failed."
    }

    Write-Host "DEPLOY SUCCESS: $active -> $target"
}
catch {
    Write-Host "DEPLOY FAILED: $($_.Exception.Message)"
    Write-Host "Starting rollback..."

    if ($configChanged) {
        Set-Content $config $originalConfig -Encoding ascii
        docker exec manga-uat-nginx nginx -t

        if ($LASTEXITCODE -eq 0) {
            docker exec manga-uat-nginx nginx -s reload
        }
    }

    if ($newCreated) {
        docker stop $targetName
        docker rm $targetName
    }

    if ($backupCreated) {
        docker rename $backupName $targetName

        if ($LASTEXITCODE -eq 0) {
            docker start $targetName
        }
    }

    throw "Deployment failed; rollback attempted. Verify UAT status."
}
