const { app, BrowserWindow } = require('electron/main')
const path = require('node:path')
const { spawn } = require('child_process')
const http = require('http')
const fs = require('fs')
const url = require('url')

let serverProcess = null
let mainWindow = null

function createLocalServer() {
  const server = http.createServer((req, res) => {
    const parsedUrl = url.parse(req.url, true)
    let filePath = path.join(__dirname, parsedUrl.pathname)
    
    // Handle root path
    if (filePath === path.join(__dirname, '/') || filePath === path.join(__dirname, '\\')) {
      filePath = path.join(__dirname, 'index.php')
    }
    
    // Check if file exists
    if (!fs.existsSync(filePath)) {
      res.writeHead(404, { 'Content-Type': 'text/html' })
      res.end('<h1>404 Not Found</h1>')
      return
    }
    
    // Handle PHP files (basic simulation)
    if (filePath.endsWith('.php')) {
      // For now, serve static HTML for PHP files
      // In a real implementation, you'd need a PHP interpreter
      if (filePath.includes('index.php')) {
        // Redirect to login.html for index.php
        res.writeHead(302, { 'Location': '/login.html' })
        res.end()
        return
      }
      
      res.writeHead(200, { 'Content-Type': 'text/html' })
      res.end(`<!-- PHP File: ${filePath} --><h1>PHP files need a proper PHP interpreter</h1>`)
      return
    }
    
    // Serve static files
    const ext = path.extname(filePath)
    const contentType = {
      '.html': 'text/html',
      '.css': 'text/css',
      '.js': 'application/javascript',
      '.png': 'image/png',
      '.jpg': 'image/jpeg',
      '.gif': 'image/gif',
      '.ico': 'image/x-icon'
    }[ext] || 'text/plain'
    
    fs.readFile(filePath, (err, data) => {
      if (err) {
        res.writeHead(500, { 'Content-Type': 'text/html' })
        res.end('<h1>500 Internal Server Error</h1>')
        return
      }
      
      res.writeHead(200, { 'Content-Type': contentType })
      res.end(data)
    })
  })
  
  server.listen(3000, () => {
    console.log('Local server running on http://localhost:3000')
  })
  
  return server
}

function createWindow () {
  // Create local server
  const server = createLocalServer()
  
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: true,
      contextIsolation: false
    }
  })

  // Load the local server
  mainWindow.loadURL('http://localhost:3000')
  
  // Open DevTools for debugging
  mainWindow.webContents.openDevTools()
  
  // Clean up on window close
  mainWindow.on('closed', () => {
    if (server) {
      server.close()
    }
    mainWindow = null
  })
}

app.whenReady().then(() => {
  createWindow()

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow()
    }
  })
})

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
  }
})

// Clean up on app quit
app.on('before-quit', () => {
  if (serverProcess) {
    serverProcess.kill()
  }
})
