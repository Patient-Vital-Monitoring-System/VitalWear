# Vercel Deployment Guide - VitalWear

## 🚀 **DEPLOYMENT OVERVIEW**

This guide explains how to deploy the VitalWear static application to Vercel.

## 📁 **PROJECT STRUCTURE**

```
VitalWear/
├── index.html (Redirect page)
├── vercel.json (Vercel config)
├── static-roles/
│   ├── index.html (Main application)
│   ├── login.html
│   ├── js/database.js
│   ├── management/
│   ├── rescuer/
│   └── responder/
└── assets/
    ├── css/
    └── logo.png
```

## ⚙️ **VERCEL CONFIGURATION**

### **vercel.json**
```json
{
  "version": 2,
  "name": "vitalwear",
  "builds": [
    {
      "src": "static-roles/**/*",
      "use": "@vercel/static"
    },
    {
      "src": "index.html",
      "use": "@vercel/static"
    }
  ],
  "routes": [
    {
      "src": "/",
      "dest": "/index.html"
    },
    {
      "src": "/static-roles/(.*)",
      "dest": "/static-roles/$1"
    },
    {
      "src": "/(.*)",
      "dest": "/static-roles/$1"
    }
  ],
  "cleanUrls": true,
  "trailingSlash": false
}
```

## 🛠️ **DEPLOYMENT METHODS**

### **Method 1: Vercel CLI (Recommended)**

1. **Install Vercel CLI**
   ```bash
   npm install -g vercel
   ```

2. **Login to Vercel**
   ```bash
   vercel login
   ```

3. **Deploy from Project Root**
   ```bash
   cd c:\xampp\htdocs\VitalWear-1
   vercel --prod
   ```

4. **Follow Prompts**
   - Set project name: `vitalwear`
   - Link to existing project if available
   - Deploy to production

### **Method 2: GitHub Integration**

1. **Push to GitHub**
   ```bash
   git add .
   git commit -m "Ready for Vercel deployment"
   git push origin main
   ```

2. **Connect to Vercel**
   - Go to [vercel.com](https://vercel.com)
   - Click "New Project"
   - Import GitHub repository
   - Configure settings (auto-detected from vercel.json)
   - Deploy

### **Method 3: Vercel Dashboard**

1. **Manual Upload**
   - Go to Vercel Dashboard
   - Click "Add New..." → "Project"
   - Drag and drop project files
   - Configure settings
   - Deploy

## 🔧 **DEPLOYMENT SETTINGS**

### **Environment Variables**
Not required for static deployment.

### **Build Settings**
- **Framework**: Static Site
- **Build Command**: Not needed
- **Output Directory**: `.` (root)
- **Install Command**: Not needed

### **Domain Configuration**
- Default: `vitalwear.vercel.app`
- Custom: Configure in Vercel Dashboard

## 📋 **DEPLOYMENT CHECKLIST**

### **Pre-Deployment**
- [ ] `vercel.json` configured correctly
- [ ] All static files in place
- [ ] Database.js working properly
- [ ] Routes tested locally
- [ ] Assets accessible

### **Post-Deployment**
- [ ] Test main page loads
- [ ] Test role selection
- [ ] Test login functionality
- [ ] Test database operations
- [ ] Test mobile responsiveness
- [ ] Verify all routes work

## 🐛 **COMMON ISSUES & SOLUTIONS**

### **Issue 1: 404 Errors**
**Problem**: Pages not found
**Solution**: Check vercel.json routes configuration

### **Issue 2: Database Not Working**
**Problem**: localStorage not accessible
**Solution**: Ensure database.js path is correct

### **Issue 3: Assets Not Loading**
**Problem**: CSS/images not loading
**Solution**: Verify asset paths in HTML

### **Issue 4: Routes Not Working**
**Problem**: Navigation broken
**Solution**: Check trailingSlash and cleanUrls settings

## 🔍 **TESTING DEPLOYMENT**

### **Local Testing**
```bash
# Start local server
python -m http.server 8000

# Or use Node.js
npx serve .
```

### **Deployment Testing**
1. Visit deployed URL
2. Test all navigation
3. Test CRUD operations
4. Test mobile view
5. Test browser compatibility

## 📊 **MONITORING**

### **Vercel Analytics**
- Page views
- Performance metrics
- Error tracking
- User analytics

### **Performance Optimization**
- Image optimization
- CSS/JS minification
- Caching strategies
- CDN distribution

## 🔄 **UPDATES & MAINTENANCE**

### **Making Updates**
1. Update files locally
2. Test changes
3. Commit to Git
4. Redeploy via Vercel

### **Rollback**
```bash
# Rollback to previous deployment
vercel rollback [deployment-url]
```

## 📞 **SUPPORT**

### **Vercel Documentation**
- [vercel.com/docs](https://vercel.com/docs)
- [Static Sites Guide](https://vercel.com/docs/concepts/static-sites)

### **Common Issues**
- Check Vercel logs for errors
- Verify file paths are correct
- Ensure vercel.json is valid JSON

## ✅ **SUCCESS CRITERIA**

Deployment is successful when:
- [ ] Main page loads at domain
- [ ] All navigation works
- [ ] Database operations function
- [ ] Mobile view responsive
- [ ] No console errors
- [ ] Performance scores good

---

**Status**: Ready for deployment
**Last Updated**: 2026-03-10
**Version**: 1.0
