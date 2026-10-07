# Deploying MediaGrab to Render.com with Docker

This guide explains how to deploy the MediaGrab Laravel Backend to **Render.com** smoothly with zero headaches using our production-ready Docker container.

---

## 🛠️ What's Already Configured for You

We have already created all the production files needed for Render:
- **`backend/Dockerfile`**: Debian Bookworm container with PHP 8.3, Nginx, Supervisor, FFmpeg, Python 3, and yt-dlp.
- **`backend/docker/nginx.conf.template`**: Nginx configured to bind dynamically to Render's `$PORT`.
- **`backend/docker/supervisord.conf`**: Automatically runs Nginx, PHP-FPM, and the Laravel Queue Worker (`queue:work`) simultaneously.
- **`backend/docker/entrypoint.sh`**: Handles port substitution, storage permissions, auto-migrations, and configuration caching.
- **`render.yaml`**: 1-click Blueprint configuration.

---

## 🚀 Step-by-Step Deployment on Render.com

### Step 1: Push your project to GitHub
Make sure your latest code is committed and pushed to GitHub:
```bash
git add .
git commit -m "Add Docker and Render configuration"
git push origin main
```

---

### Step 2: Create a New Web Service on Render
1. Go to [dashboard.render.com](https://dashboard.render.com) and log in.
2. Click **"New +"** in the top right $\rightarrow$ select **"Web Service"**.
3. Choose **"Build and deploy from a Git repository"** $\rightarrow$ Click **Next**.
4. Select your **MediaGrab** repository.

---

### Step 3: Configure Service Settings
Fill in the following settings:

| Setting | Value |
|---|---|
| **Name** | `mediagrab-backend` |
| **Region** | Oregon (US West) or Frankfurt (EU) |
| **Branch** | `main` |
| **Root Directory** | `backend` |
| **Runtime** | **Docker** |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context** | `.` |
| **Instance Type** | Free (or Starter) |

---

### Step 4: Add Environment Variables
Under the **"Environment Variables"** section in Render, add:

| Key | Value | Description |
|---|---|---|
| `APP_NAME` | `MediaGrab` | App Name |
| `APP_ENV` | `production` | Environment |
| `APP_DEBUG` | `false` | Disable debug stack traces |
| `APP_KEY` | *(Click "Generate" or copy from `.env`)* | 32-character key |
| `DB_CONNECTION` | `sqlite` | Built-in zero-config database |
| `QUEUE_CONNECTION` | `database` | Background queue jobs |
| `YTDLP_BINARY` | `yt-dlp` | Pre-installed binary |
| `FFMPEG_BINARY` | `ffmpeg` | Pre-installed binary |
| `MAX_MEDIA_DURATION` | `1800` | Max 30 minutes |
| `MAX_DOWNLOAD_SIZE_MB`| `500` | Max 500 MB |
| `DOWNLOAD_TIMEOUT` | `300` | 5 minute timeout |
| `TEMP_FILE_TTL_HOURS`| `2` | Purge files after 2 hours |

*(Note: If you have an external MySQL database from Railway, Aiven, or Supabase, set `DB_CONNECTION=mysql` and provide `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

---

### Step 5: Deploy
1. Click **"Create Web Service"**.
2. Render will pull the Dockerfile, install PHP 8.3, FFmpeg, Python, yt-dlp, install Composer dependencies, and start the container.
3. Once deployed, Render will show:
   ```
   ==> Starting Supervisord (PHP-FPM, Nginx, Queue Worker)...
   ==> Your service is live at: https://mediagrab-backend.onrender.com
   ```

---

### Step 6: Connect to Netlify Frontend
1. Copy your Render backend URL (e.g. `https://mediagrab-backend.onrender.com`).
2. Go to your Netlify dashboard for your frontend site.
3. Go to **Site configuration** $\rightarrow$ **Environment variables**.
4. Add:
   - **Key**: `VITE_API_URL`
   - **Value**: `https://mediagrab-backend.onrender.com/api`
5. Trigger a redeploy on Netlify. Done!
