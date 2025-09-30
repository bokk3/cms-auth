# Composer Dependencies Setup

This project uses Composer for dependency management. After cloning this repository:

## 1. Install Dependencies

```bash
composer install
```

This will recreate the `vendor/` directory and install all required packages including:
- Stripe PHP SDK
- vlucas/phpdotenv for environment variable loading

## 2. Environment Configuration

Copy `.env.example` to `.env` and update with your actual values:

```bash
cp .env.example .env
```

Update the following in your `.env` file:
- Database credentials
- Stripe API keys (get these from your Stripe Dashboard)
- Other environment-specific settings

## 3. Docker Setup

Make sure Docker and Docker Compose are installed, then:

```bash
# Start the application stack
docker-compose up -d

# Check that all services are running
docker-compose ps
```

## Security Notes

- **NEVER** commit the `vendor/` directory to git
- **NEVER** commit the `.env` file with real credentials
- Always use `.env.example` as a template for required environment variables
- Keep your Stripe API keys secure and use test keys for development

## Troubleshooting

If you encounter dependency issues:

```bash
# Clear Composer cache
composer clear-cache

# Update dependencies
composer update

# Reinstall from scratch
rm -rf vendor/
composer install
```