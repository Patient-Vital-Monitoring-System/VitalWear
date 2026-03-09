# 🚀 Vercel Deployment Guide

## 📋 Overview

This guide covers deploying the VitalWear patient vital monitoring system to Vercel, a modern serverless platform that supports PHP applications.

## 🎯 Vercel Configuration

### 📁 vercel.json Configuration

The `vercel.json` file has been created with the following settings:

```json
{
  "version": 2,
  "name": "vitalwear",
  "builds": [
    {
      "src": "index.php",
      "use": "@vercel/php"
    }
  ],
  "routes": [
    {
      "src": "/index.php",
      "dest": "/index.php"
    },
    {
      "src": "/login.html",
      "dest": "/login.html"
    },
    {
      "src": "/roles",
      "dest": "/roles"
    },
    {
      "src": "/assets",
      "dest": "/assets"
    },
    {
      "src": "/api",
      "dest": "/api"
    },
    {
      "src": "/database",
      "dest": "/database"
    }
  ],
  "env": {
    "VERCEL_ENV": "production"
  },
  "functions": {
    "api/**/*.php": {
      "runtime": "php@8.2",
      "maxDuration": 10
    }
  },
  "rewrites": [
    {
      "source": "/(.*)",
      "destination": "/index.php"
    }
  ]
}
```

### 🔧 Configuration Explained

- **Build Settings**: Uses Vercel's PHP runtime
- **Routes**: Maps all files to their correct destinations
- **Environment**: Sets production environment
- **Functions**: PHP 8.2 runtime with 10-second timeout
- **Rewrites**: Routes all requests through index.php (WordPress-style)

## 🗄️ Database Setup for Vercel

### 🌐 External Database (Recommended)

Vercel doesn't support MySQL directly, so use an external database:

#### Option 1: PlanetScale (Recommended)
```bash
# Create PlanetScale account
# Get connection string
DATABASE_URL="mysql://user:password@aws.connect.psdb.cloud:xxxx/vitalwear"
```

#### Option 2: Railway
```bash
# Create Railway account
# Add MySQL database
DATABASE_URL="mysql://user:password@containers.railway.app:xxxx/railway"
```

#### Option 3: Supabase
```bash
# Create Supabase project
# Get connection details
DATABASE_URL="postgresql://user:password@xxxx.supabase.co:5432/postgres"
```

### 🔧 Database Configuration

Create `database/connection.php` for Vercel:

```php
<?php
// Vercel Database Configuration
$db_url = getenv('DATABASE_URL') ?? '';

if ($db_url) {
    // Parse database URL
    $url = parse_url($db_url);
    
    $host = $url['host'];
    $port = $url['port'] ?? 3306;
    $user = $url['user'];
    $password = $url['pass'];
    $database = ltrim($url['path'], '/');
    
    // Create connection
    $conn = new mysqli($host, $user, $password, $database, $port);
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} else {
    // Fallback for local development
    $conn = new mysqli('localhost', 'root', '', 'vitalwear');
}

function getDBConnection() {
    global $conn;
    return $conn;
}
?>
```

## 🚀 Deployment Steps

### 📋 Prerequisites
- **Vercel Account**: Free account at vercel.com
- **GitHub Repository**: Code pushed to GitHub
- **External Database**: MySQL/PostgreSQL database
- **Domain**: Custom domain (optional)

### 🎯 Step-by-Step Deployment

#### Step 1: Prepare Repository
```bash
# Ensure vercel.json is in root
git add vercel.json
git commit -m "Add Vercel configuration"

# Push to GitHub
git push origin main
```

#### Step 2: Install Vercel CLI
```bash
# Install Vercel CLI
npm i -g vercel

# Login to Vercel
vercel login
```

#### Step 3: Deploy Project
```bash
# Deploy from root directory
cd /path/to/VitalWear-1

# Deploy to Vercel
vercel

# Follow prompts:
# - Link to existing project (or create new)
# - Import environment variables
# - Deploy to production
```

#### Step 4: Configure Environment
```bash
# Set environment variables in Vercel dashboard
# - DATABASE_URL: Your database connection string
# - APP_ENV: production
# - SESSION_SECRET: Random secret string
```

## 🔧 Environment Variables

### 📁 Required Variables

In Vercel dashboard, set these environment variables:

```bash
DATABASE_URL=mysql://username:password@host:port/database_name
APP_ENV=production
SESSION_SECRET=your_random_secret_string_here
```

### 🔒 Security Variables

```bash
# Generate secure session secret
SESSION_SECRET=$(openssl rand -base64 32)

# Additional security headers
SECURITY_HEADERS=true
```

## 🗄️ Database Migration

### 🚀 Run Migration on Vercel

Since Vercel is serverless, run migration via:

#### Option 1: Vercel Function
```php
// api/migrate.php
<?php
header('Content-Type: application/json');

try {
    include '../database/connection.php';
    $conn = getDBConnection();
    
    // Run migration
    $sql = "ALTER TABLE incident MODIFY COLUMN log_id INT DEFAULT NULL";
    $conn->query($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

#### Option 2: One-time Script
```bash
# Visit migration URL
curl https://your-app.vercel.app/api/migrate.php

# Or use Vercel CLI
vercel env pull
vercel env add DATABASE_URL=mysql://user:pass@host:port/db
```

## 📱 Mobile & Desktop Support

### ✅ Responsive Design

Vercel deployment maintains all responsive features:

- **Mobile Responsive**: Built-in responsive design works perfectly
- **Touch Interface**: Touch-friendly buttons and forms
- **Cross-Platform**: Works on iOS, Android, desktop
- **Progressive Web App**: Can be added to home screen

### 🎯 Performance Optimization

#### Vercel Edge Network
- **Global CDN**: Automatic CDN distribution
- **Edge Caching**: Built-in caching at edge
- **Image Optimization**: Automatic image optimization
- **Compression**: Gzip/Brotli compression

#### Database Performance
- **Connection Pooling**: Efficient database connections
- **Query Optimization**: Optimized SQL queries
- **Index Usage**: Proper database indexes

## 🔧 Custom Domain Setup

### 🌐 Domain Configuration

#### Step 1: Add Domain in Vercel
```bash
# In Vercel dashboard
# Project Settings → Domains
# Add custom domain: vitalwear.yourdomain.com
```

#### Step 2: Configure DNS
```bash
# DNS Records (example)
A    vitalwear     cname.vercel-dns.com
CNAME www      cname.vercel-dns.com
```

#### Step 3: SSL Certificate
- ✅ **Automatic SSL**: Vercel provides free SSL
- ✅ **HTTPS Only**: Automatic redirect to HTTPS
- ✅ **Certificate Renewal**: Automatic renewal

## 📊 Monitoring & Analytics

### 📈 Vercel Analytics

Vercel provides built-in analytics:

```bash
# View analytics
vercel logs vitalwear

# Real-time metrics
vercel analytics vitalwear

# Performance insights
vercel insights vitalwear
```

### 🔍 Custom Monitoring

Add custom monitoring:

```php
// Add to index.php
// Performance monitoring
$start_time = microtime(true);

// At end of script
$end_time = microtime(true);
$execution_time = ($end_time - $start_time) * 1000;

// Log performance
if (getenv('APP_ENV') === 'production') {
    error_log("Page execution time: {$execution_time}ms");
}
```

## 🔄 CI/CD Integration

### 🚀 GitHub Actions

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Vercel

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
          
      - name: Install Vercel CLI
        run: npm install -g vercel
        
      - name: Pull Vercel Environment
        run: vercel env pull
        
      - name: Deploy to Vercel
        run: vercel --prod --token=${{ secrets.VERCEL_TOKEN }}
```

## 🚨 Troubleshooting

### ⚠️ Common Issues

#### Database Connection Errors
```bash
# Check environment variables
vercel env ls

# Test database connection
curl -X POST https://your-app.vercel.app/api/test-db.php
```

#### Static File Issues
```bash
# Check build output
vercel build

# Verify routes
vercel ls
```

#### Performance Issues
```bash
# Check Vercel logs
vercel logs vitalwear

# Monitor response times
curl -w "@{time_total}\n" -o /dev/null -s https://your-app.vercel.app/
```

### 🔧 Debug Mode

Enable debugging on Vercel:

```php
// Add to top of PHP files
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
```

## 📋 Deployment Checklist

### ✅ Pre-Deployment
- [ ] Repository pushed to GitHub
- [ ] vercel.json configured
- [ ] Environment variables set
- [ ] External database provisioned
- [ ] Custom domain configured (optional)

### ✅ Post-Deployment
- [ ] Database migration completed
- [ ] All pages load correctly
- [ ] Mobile responsiveness verified
- [ ] API endpoints working
- [ ] SSL certificate active
- [ ] Monitoring configured

### ✅ Production Ready
- [ ] Debug mode disabled
- [ ] Error logging configured
- [ ] Performance monitoring active
- [ ] Backup strategy in place
- [ ] Security headers set
- [ ] Custom domain pointing correctly

## 🎯 Benefits of Vercel Deployment

### 🚀 Performance
- **Global CDN**: Automatic edge distribution
- **Zero Cold Starts**: Serverless functions
- **Auto-scaling**: Built-in scaling
- **HTTP/3**: Modern protocol support

### 💰 Cost Benefits
- **Free Tier**: Generous free tier available
- **Pay-per-use**: Only pay for actual usage
- **No server costs**: No server maintenance
- **SSL included**: Free SSL certificates

### 🛡️ Security
- **DDoS Protection**: Built-in DDoS mitigation
- **Edge Security**: Security at edge locations
- **Automatic Updates**: Platform security updates
- **Isolation**: Serverless function isolation

---

**🎉 Your VitalWear system is now ready for modern Vercel deployment with excellent performance, global CDN, and automatic scaling!**
