# Laravel Production Deployment Guide
Date: 2026-03-22  
Author: Ye Kyaw Aung  

---
## Initial Deploy at Production

### 1. Update `app.blade.php`

#### Remove these:
- `@viteReactRefresh`
- `"resources/js/pages/{$page['component']}.tsx"`

#### Final version:
```blade
@routes
@vite('resources/js/app.tsx')
@inertiaHead
```

---

## 2. Clear Laravel Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

---

## 3. Remove Old Build & Cache Files

```bash
rm -rf bootstrap/cache/*
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
rm -rf storage/framework/sessions/*
```

---

## 4. Reinstall & Build Frontend

```bash
rm -rf node_modules
rm -rf public/build
npm install
npm run build
```

---

## 5. Next Time Deploy at Production

```bash
php artisan optimize:clear
npm run build
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

---

## Notes

- Always clear cache before rebuilding
- Ensure correct `@vite` usage in production
- Restart services after deployment to apply changes

---

*End of Notes*