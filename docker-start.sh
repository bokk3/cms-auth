#!/bin/bash

# CMS Auth Docker Stack Management Script

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[CMS-AUTH]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker and Docker Compose are installed
check_requirements() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed or not in PATH"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_error "Docker Compose is not installed or not in PATH"
        exit 1
    fi
}

# Start the stack
start_stack() {
    print_status "Starting CMS Auth Docker stack..."
    
    # Build and start services
    docker-compose up --build -d
    
    print_status "Waiting for services to be healthy..."
    sleep 10
    
    # Check service health
    if docker-compose ps | grep -q "Up (healthy)"; then
        print_success "Stack started successfully!"
        show_access_info
    else
        print_warning "Stack started but some services may not be fully ready yet."
        print_status "Run './docker-start.sh status' to check service health."
    fi
}

# Stop the stack
stop_stack() {
    print_status "Stopping CMS Auth Docker stack..."
    docker-compose down
    print_success "Stack stopped successfully!"
}

# Show service status
show_status() {
    print_status "Service Status:"
    docker-compose ps
    echo
    
    print_status "Service Health:"
    for service in web mariadb phpmyadmin redis; do
        health=$(docker inspect cms-auth-${service} --format='{{.State.Health.Status}}' 2>/dev/null || echo "not running")
        case $health in
            "healthy")
                print_success "$service: Healthy ✓"
                ;;
            "unhealthy")
                print_error "$service: Unhealthy ✗"
                ;;
            "starting")
                print_warning "$service: Starting..."
                ;;
            *)
                print_warning "$service: $health"
                ;;
        esac
    done
}

# Show access information
show_access_info() {
    echo
    print_success "=== CMS Auth System Ready! ==="
    echo
    print_status "🌐 Web Application: http://localhost:8083"
    print_status "🗄️  PHPMyAdmin:     http://localhost:8084"
    print_status "📊 Redis:          localhost:6380"
    print_status "🐘 MariaDB:        localhost:3308"
    echo
    print_status "📝 Test Accounts:"
    echo "   Admin:  admin / admin123      (Full access)"
    echo "   Editor: editor / editor123    (Content management)"
    echo "   User:   alex_tech / editor123 (Profile only)"
    echo
    print_status "🔧 Database Credentials:"
    echo "   Root:     root / \${DB_ROOT_PASSWORD}"
    echo "   App User: \${DB_USER} / \${DB_PASS}"
    echo "   Database: login_system"
}

# View logs
show_logs() {
    service=${1:-web}
    print_status "Showing logs for $service service (Ctrl+C to exit)..."
    docker-compose logs -f $service
}

# Clean up everything
cleanup() {
    print_status "Cleaning up CMS Auth Docker resources..."
    docker-compose down -v --remove-orphans
    docker system prune -f
    print_success "Cleanup completed!"
}

# Main script logic
case "${1:-}" in
    "start"|"up")
        check_requirements
        start_stack
        ;;
    "stop"|"down")
        stop_stack
        ;;
    "restart")
        check_requirements
        stop_stack
        sleep 2
        start_stack
        ;;
    "status")
        show_status
        ;;
    "info")
        show_access_info
        ;;
    "logs")
        show_logs $2
        ;;
    "cleanup"|"clean")
        cleanup
        ;;
    "help"|"--help"|"-h")
        echo "CMS Auth Docker Stack Management"
        echo
        echo "Usage: $0 [COMMAND]"
        echo
        echo "Commands:"
        echo "  start, up       Start the Docker stack"
        echo "  stop, down      Stop the Docker stack"
        echo "  restart         Restart the Docker stack"
        echo "  status          Show service status"
        echo "  info            Show access information"
        echo "  logs [service]  Show logs (default: web)"
        echo "  cleanup, clean  Remove all containers, volumes and images"
        echo "  help            Show this help message"
        ;;
    *)
        print_error "Unknown command: ${1:-}"
        print_status "Run '$0 help' for usage information."
        exit 1
        ;;
esac