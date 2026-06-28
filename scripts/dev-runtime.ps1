param(
    [int] $Port = 8010,
    [string] $Volume = "freight-exchange-runtime",
    [string] $Container = "freight-exchange-app",
    [switch] $BuildAssets,
    [switch] $Fresh,
    [switch] $Seed
)

$ErrorActionPreference = "Stop"

function Invoke-Step {
    param(
        [string] $Title,
        [scriptblock] $Action
    )

    Write-Host "==> $Title" -ForegroundColor Cyan
    & $Action
}

function Get-DemoCounts {
    param(
        [string] $Container
    )

    $counts = docker exec -w /app $Container php artisan tinker --execute="echo App\Models\User::count().':'.App\Models\FreightLoad::count().':'.App\Models\Vehicle::count();"
    $parts = $counts.Trim().Split(":")

    if ($parts.Length -ne 3) {
        return @{ Users = 0; Loads = 0; Vehicles = 0; Ready = $false }
    }

    $users = [int] $parts[0]
    $loads = [int] $parts[1]
    $vehicles = [int] $parts[2]

    return @{
        Users = $users
        Loads = $loads
        Vehicles = $vehicles
        Ready = $users -gt 0 -and $loads -gt 0 -and $vehicles -gt 0
    }
}

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$image = "freight-exchange-runtime-php84:latest"
$router = "/app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

Invoke-Step "Checking Docker" {
    docker version | Out-Null
}

Invoke-Step "Building PHP runtime image" {
    docker build `
        -f (Join-Path $PSScriptRoot "dev-runtime.Dockerfile") `
        -t $image `
        $root | Out-Null
}

if ($BuildAssets) {
    Invoke-Step "Building frontend assets" {
        Push-Location $root
        try {
            npm.cmd run build
        } finally {
            Pop-Location
        }
    }
}

Invoke-Step "Preparing Docker volume $Volume" {
    docker volume create $Volume | Out-Null

    if ($Fresh) {
        docker run --rm `
            -v "${Volume}:/app" `
            alpine:3.20 `
            sh -lc "rm -rf /app/* /app/.[!.]* /app/..?*"
    }
}

Invoke-Step "Syncing source into Linux volume" {
    $source = $root -replace "\\", "/"
    docker run --rm `
        -v "${source}:/src:ro" `
        -v "${Volume}:/app" `
        -w /src `
        $image `
        sh -lc "tar --exclude='./.git' --exclude='./node_modules' --exclude='./vendor' --exclude='./storage/framework/cache/data/*' --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' --exclude='./storage/logs/*' -cf - . | tar -xf - -C /app && mkdir -p /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache && chmod -R 777 /app/storage /app/bootstrap/cache"
}

Invoke-Step "Installing PHP dependencies in volume" {
    docker run --rm `
        -v "${Volume}:/app" `
        -w /app `
        $image `
        composer install --no-interaction --prefer-dist --optimize-autoloader
}

Invoke-Step "Stopping previous runtime container" {
    docker rm -f $Container 2>$null | Out-Null
}

Invoke-Step "Starting runtime container on port $Port" {
    docker run -d `
        --name $Container `
        -p "${Port}:8000" `
        -v "${Volume}:/app" `
        -w /app/public `
        $image `
        php -d opcache.enable_cli=1 -S 0.0.0.0:8000 $router | Out-Null
}

Invoke-Step "Preparing Laravel" {
    docker exec -w /app $Container php artisan optimize:clear
    docker exec -w /app $Container sh -lc "grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force"
    docker exec -w /app $Container sh -lc "grep -q '^APP_URL=' .env && sed -i 's#^APP_URL=.*#APP_URL=http://127.0.0.1:$Port#' .env || printf '\nAPP_URL=http://127.0.0.1:$Port\n' >> .env"
    docker exec -w /app $Container php artisan storage:link --force
    docker exec -w /app $Container php artisan migrate --force

    $demoCounts = Get-DemoCounts -Container $Container

    if ($Seed -or -not $demoCounts.Ready) {
        Write-Host "Seeding freight demo data" -ForegroundColor DarkCyan
        docker exec -w /app $Container php artisan db:seed --class=FreightExchangeSeeder --force

        $demoCounts = Get-DemoCounts -Container $Container

        if (-not $demoCounts.Ready) {
            Write-Host "Retrying demo seed through DatabaseSeeder" -ForegroundColor DarkCyan
            docker exec -w /app $Container php artisan db:seed --force
            $demoCounts = Get-DemoCounts -Container $Container
        }

        if (-not $demoCounts.Ready) {
            throw "Demo seed did not create required records. users=$($demoCounts.Users), loads=$($demoCounts.Loads), vehicles=$($demoCounts.Vehicles)"
        }
    }

    docker exec -w /app $Container php artisan optimize
}

Write-Host ""
Write-Host "Runtime is ready: http://127.0.0.1:$Port" -ForegroundColor Green
Write-Host "Container: $Container"
Write-Host "Volume:    $Volume"
