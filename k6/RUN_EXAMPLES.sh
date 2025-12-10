#!/bin/bash
# k6 Load Testing - Run Examples
# Deschide News App

echo "================================================"
echo "  k6 Load Testing Suite - Deschide News App"
echo "================================================"
echo ""

# Check if k6 is installed
if ! command -v k6 &> /dev/null; then
    echo "❌ Error: k6 is not installed"
    echo "Install with: sudo apt-get install k6"
    exit 1
fi

echo "✓ k6 version: $(k6 version | head -1)"
echo ""

# Check if backend is running
if ! curl -s http://127.0.0.1:8081/api > /dev/null; then
    echo "⚠️  Warning: Backend not responding on http://127.0.0.1:8081"
    echo "Start backend with:"
    echo "  cd /var/www/deschide_news_app/apps/backend"
    echo "  symfony serve -d --port=8081"
    echo ""
fi

echo "================================================"
echo "  Available Tests"
echo "================================================"
echo ""
echo "1. Load Test (10 min, 100 VUs)"
echo "   k6 run load-test.js"
echo ""
echo "2. Stress Test (14 min, 500 VUs)"
echo "   k6 run stress-test.js"
echo ""
echo "3. Soak Test (70 min, 50 VUs)"
echo "   k6 run soak-test.js"
echo ""
echo "4. Spike Test (5 min, 500 VUs)"
echo "   k6 run scenarios/spike-test.js"
echo ""
echo "5. API Endpoints Test (5 min, 50 VUs)"
echo "   k6 run scenarios/api-endpoints.js"
echo ""
echo "6. User Journey Test (10 min, 20 VUs)"
echo "   k6 run scenarios/user-journey.js"
echo ""
echo "================================================"
echo "  Quick Examples"
echo "================================================"
echo ""
echo "# Run load test with JSON output"
echo "k6 run --out json=results/load-test.json load-test.js"
echo ""
echo "# Run stress test with custom backend URL"
echo "k6 run -e BACKEND_URL=http://api.deschide.local stress-test.js"
echo ""
echo "# Run shorter test (1 minute)"
echo "k6 run --duration 1m --vus 10 load-test.js"
echo ""
echo "================================================"
echo ""

# Ask which test to run
read -p "Run a test now? (1-6, or 0 to exit): " choice

case $choice in
    1)
        echo "Running Load Test..."
        k6 run load-test.js
        ;;
    2)
        echo "Running Stress Test..."
        k6 run stress-test.js
        ;;
    3)
        echo "Running Soak Test (this will take ~70 minutes)..."
        read -p "Are you sure? (y/n): " confirm
        if [ "$confirm" = "y" ]; then
            k6 run soak-test.js
        fi
        ;;
    4)
        echo "Running Spike Test..."
        k6 run scenarios/spike-test.js
        ;;
    5)
        echo "Running API Endpoints Test..."
        k6 run scenarios/api-endpoints.js
        ;;
    6)
        echo "Running User Journey Test..."
        k6 run scenarios/user-journey.js
        ;;
    0)
        echo "Exiting..."
        exit 0
        ;;
    *)
        echo "Invalid choice"
        exit 1
        ;;
esac
