# CBT UNBK - MAN 2 Lombok Timur

Aplikasi Ujian Berbasis Komputer berbasis WordPress + Docker.

## Quick Start (Lokal)

```bash
docker compose up -d --build
```

Buka http://localhost:8088

## Deploy ke Server

```bash
./deploy.sh
```

## Struktur

```
├── docker-compose.yml
├── Dockerfile              # WordPress + ionCube Loader
├── php-custom.ini          # PHP config
├── wp-content/
│   ├── themes/unbk/        # Theme Bimasoft (ionCube)
│   └── plugins/
│       └── cbt-settings-fix/  # Plugin fix settings
├── database/               # SQL dumps & migrations
├── deploy.sh
└── .env.example
```

