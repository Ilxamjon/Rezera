# Quick helpers for Docker-based Rezera API (PowerShell)

param(
    [ValidateSet('up', 'down', 'logs', 'key', 'smoke')]
    [string]$Action = 'up'
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host 'Docker topilmadi. Docker Desktop o‘rnating: https://www.docker.com/products/docker-desktop/' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path .env)) {
    Copy-Item .env.docker.example .env
    Write-Host '.env yaratildi (.env.docker.example dan). DB_PASSWORD va APP_KEY ni tekshiring.' -ForegroundColor Yellow
}

switch ($Action) {
    'key' {
        docker compose run --rm -e RUN_MIGRATIONS=0 -e CACHE_CONFIG=0 app php artisan key:generate --force
    }
    'up' {
        docker compose up -d --build
        Write-Host 'Health: http://localhost:8080/api/v1/health' -ForegroundColor Green
    }
    'down' {
        docker compose down
    }
    'logs' {
        docker compose logs -f --tail=100
    }
    'smoke' {
        docker compose exec app php artisan rezera:smoke
    }
}
