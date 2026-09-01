# Migrasi Inertia → React SPA + Laravel API

Dokumen ini mencatat migrasi total dari stack **Laravel + Inertia + React** (Fortify-based starter kit) menjadi **Laravel sebagai REST API murni** dengan **React SPA** yang berdiri sendiri (client-side routing via React Router, client-side data fetching via axios, autentikasi berbasis Sanctum SPA/cookie).

Arsitektur dipersiapkan untuk kebutuhan real-time berikutnya (Laravel Reverb / WebSocket untuk notifikasi approval mode manual di sistem monitoring portal parkir IoT), meski WebSocket belum diimplementasikan di migrasi ini.

---

## 1. Ringkasan Stack

| | Sebelum | Sesudah |
|---|---|---|
| Rendering | Inertia (`Inertia::render()`) | React SPA murni (`view('app')` catch-all + client-side routing) |
| Routing frontend | Server-driven (Inertia visit) | `react-router-dom` (client-side) |
| Auth | Session Inertia + Fortify (2FA, passkeys) | Sanctum SPA (cookie-based) + Fortify (login/register/logout saja) |
| State/data fetching | Props dari controller (`Inertia::render($props)`) | `axios` ke endpoint JSON (`/api/*`), state di `zustand` |
| Bahasa frontend | TypeScript (`.tsx`) | Tetap TypeScript (`.tsx`) |
| Build tool | `vite-plus` (wrapper custom) | `vite` + `laravel-vite-plugin` + `@vitejs/plugin-react` standar |

---

## 2. Audit awal & keputusan scope

Project ternyata bukan Breeze standar, melainkan **Laravel React Starter Kit berbasis Fortify** (bukan Breeze) dengan fitur 2FA, passkeys (WebAuthn), dan `wayfinder` (route helper generator). Juga ditemukan `package.json` & `vite.config.ts` sudah punya perubahan belum-commit yang tidak lengkap dan membuat build rusak (referensi file yang belum ada, plugin wayfinder/tailwind/inertia hilang) — file ini di-*revert* dulu ke versi committed sebelum migrasi dimulai dari kondisi bersih.

Tiga keputusan scope dikonfirmasi di awal:

1. **Tetap TypeScript** (bukan diturunkan ke `.jsx`) — mengikuti konvensi 145 file yang sudah ada.
2. **Auth disederhanakan ke email+password dulu** — fitur 2FA & passkeys dinonaktifkan sementara (bisa diaktifkan lagi sebagai task terpisah), karena migrasi ke SPA murni sudah cukup besar sendiri.
3. **Revert dulu file yang rusak**, migrasi dilakukan dari baseline yang utuh.

---

## 3. Perubahan Backend (Laravel)

### 3.1 Dependency

- **Dihapus** (npm): `@inertiajs/react`, `@inertiajs/vite`.
- **Dihapus** (composer): `inertiajs/inertia-laravel`.
- **Ditambah** (composer): `laravel/sanctum`.
- **Ditambah** (npm): `react-router-dom`, `axios` (sudah ada), `zustand`.

### 3.2 Middleware & routing dasar (`bootstrap/app.php`)

- `HandleInertiaRequests` dihapus dari middleware `web`.
- Registrasi `api: __DIR__.'/../routes/api.php'` ditambahkan ke `withRouting()`.
- `EnsureFrontendRequestsAreStateful` (Sanctum) di-*prepend* ke middleware group `api` — ini yang membuat request dari SPA (yang mengirim cookie session, bukan Bearer token) dikenali sebagai request "stateful" oleh Sanctum.

### 3.3 Sanctum SPA auth

- `config/sanctum.php` dipublish (stateful domains, guard `web`).
- `config/cors.php` dibuat baru:
  - `paths`: `['api/*', 'sanctum/csrf-cookie']`
  - `allowed_origins`: dari `env('FRONTEND_URL')`
  - `supports_credentials: true` — wajib true agar browser mau mengirim cookie cross-origin (beda port dianggap origin berbeda).
- `.env` / `.env.example` menambahkan:
  ```
  SESSION_DOMAIN=localhost
  FRONTEND_URL=http://localhost:5173
  SANCTUM_STATEFUL_DOMAINS=localhost:5173
  VITE_API_URL="${APP_URL}"
  ```
  **Kenapa `SESSION_DOMAIN=localhost`?** Browser mengirim request dari `localhost:5173` (Vite dev server) ke `localhost:8000` (Laravel) — beda port dianggap origin berbeda oleh browser. Cookie session dengan atribut `Domain=localhost` (tanpa port) tetap ikut terkirim ke kedua port tersebut karena domain cookie tidak memperhitungkan port. Tanpa ini, cookie sesi Laravel tidak akan pernah sampai ke request API dari SPA.
  **Kenapa `SANCTUM_STATEFUL_DOMAINS`?** Ini whitelist domain yang boleh melakukan autentikasi cookie-based ke Sanctum. Request dari domain di luar daftar ini akan diperlakukan sebagai request token-based biasa (butuh Bearer token), bukan cookie session — jadi domain frontend SPA wajib terdaftar di sini.

### 3.4 Fortify (`app/Providers/FortifyServiceProvider.php`, `config/fortify.php`)

- `config('fortify.views')` diset `false` — Fortify tidak lagi meregister route GET yang me-render view (login page, register page, dst.), karena React yang menangani rendering.
- `Features::twoFactorAuthentication()` dan `Features::passkeys()` **dihapus** dari array `features` — dinonaktifkan sesuai keputusan penyederhanaan scope.
- `configureViews()` (yang sebelumnya mendaftarkan `Inertia::render()` untuk tiap halaman auth) **dihapus total** dari service provider.
- **Fortify tetap dipertahankan untuk endpoint `POST /login`, `POST /register`, `POST /logout`** — Fortify secara built-in sudah mengembalikan response JSON yang sesuai untuk request XHR/SPA (mendeteksi header `Accept: application/json` / `X-Requested-With`), jadi tidak perlu dibuat ulang sebagai controller custom. Ini juga persis pola yang diminta: SPA memanggil `/sanctum/csrf-cookie` dulu, baru `POST /login`.

### 3.5 Controller & route API baru

- `app/Http/Controllers/Api/AuthController.php` — hanya `me()` (`GET /api/me`), karena login/register/logout sudah ditangani Fortify.
- `app/Http/Controllers/Api/ProfileController.php` — `show`, `update`, `destroy` (migrasi dari `Settings\ProfileController`, return `response()->json()` bukan `Inertia::render()`).
- `app/Http/Controllers/Api/PasswordController.php` — `update` (migrasi dari `Settings\SecurityController`, minus bagian 2FA/passkey).
- `routes/api.php` (baru):
  ```php
  Route::middleware('auth:sanctum')->group(function () {
      Route::get('me', [AuthController::class, 'me']);
      Route::get('profile', [ProfileController::class, 'show']);
      Route::patch('profile', [ProfileController::class, 'update']);
      Route::delete('profile', [ProfileController::class, 'destroy']);
      Route::put('password', [PasswordController::class, 'update']);
  });
  ```
- `routes/web.php` disederhanakan jadi satu catch-all:
  ```php
  Route::view('/{any}', 'app')->where('any', '.*');
  ```
  Semua path selain `/api/*` dan `/sanctum/*` akan menerima HTML SPA shell yang sama, lalu React Router yang menentukan halaman mana yang tampil di client.
- `routes/settings.php` dan controller `app/Http/Controllers/Settings/*` **dihapus** — fungsinya sudah sepenuhnya pindah ke `Api/*`, tidak ada lagi yang `require` file ini.

### 3.6 Blade template (`resources/views/app.blade.php`)

Disederhanakan jadi HTML shell kosong:
```blade
<div id="root"></div>
@vite(['resources/css/app.css', 'resources/js/main.tsx'])
```
Semua directive Inertia (`x-inertia::head`, `x-inertia::app`, `data-page`, `@vite(...{$page['component']}.tsx)`) dihapus. Script deteksi dark-mode & style anti-FOUC dipertahankan (tidak terkait Inertia).

---

## 4. Perubahan Frontend (React)

### 4.1 Struktur folder

```
resources/js/
├── pages/          # sudah lowercase, tidak perlu rename
├── components/     # reusable components (existing)
├── layouts/         # existing, sebagian ditulis ulang
├── hooks/           # existing, sebagian ditulis ulang
├── services/        # BARU — axios client
│   └── api.ts
├── store/           # BARU — zustand state
│   └── auth.ts
├── App.tsx           # BARU — React Router routes
└── main.tsx          # BARU — entry point (ganti app.tsx)
```

### 4.2 `services/api.ts`

Axios instance dengan:
- `baseURL`: dari `VITE_API_URL` (fallback ke `window.location.origin`)
- `withCredentials: true` & `withXSRFToken: true` — wajib untuk auth berbasis cookie Sanctum.
- Helper `ensureCsrfCookie()` — memanggil `/sanctum/csrf-cookie` sebelum request state-changing pertama (login/register), sesuai kontrak Sanctum SPA.
- Interceptor response: jika status `401` dan bukan sedang di halaman `/login`, redirect paksa ke `/login`.

### 4.3 `store/auth.ts`

Zustand store sederhana: `user`, `status` (`idle | loading | authenticated | guest`), `fetchUser()`, `setUser()`, `logout()`.

### 4.4 `main.tsx` & `App.tsx`

- `main.tsx` — entry point baru, render `<App />` di dalam `<BrowserRouter>` (ganti `app.tsx` yang sebelumnya memanggil `createInertiaApp()`).
- `App.tsx` — definisi route:
  - **Publik** (`GuestOnlyRoute` — redirect ke `/` kalau sudah login): `/login`, `/register`, `/forgot-password`, `/reset-password/:token`.
  - **Terproteksi** (`ProtectedRoute` — redirect ke `/login` kalau belum login): `/`, `/settings/profile`, `/settings/appearance`, `/verify-email`.
  - Status auth dicek sekali di awal mount via `fetchUser()` (memanggil `GET /api/me`).

### 4.5 Halaman auth & settings

Semua halaman di `pages/auth/*` dan `pages/settings/*` ditulis ulang: dari `<Form {...store.form()}>` (Inertia) + `useForm` menjadi `useState` lokal per field + `handleSubmit` yang memanggil `axios` langsung, dengan error validasi (422) di-parse dari `error.response.data.errors`.

| Halaman | Endpoint yang dipanggil |
|---|---|
| `login.tsx` | `POST /sanctum/csrf-cookie` → `POST /login` → `GET /api/me` |
| `register.tsx` | `POST /sanctum/csrf-cookie` → `POST /register` → `GET /api/me` |
| `forgot-password.tsx` | `POST /forgot-password` |
| `reset-password.tsx` | `POST /reset-password` (token & email dari URL) |
| `verify-email.tsx` | `POST /email/verification-notification` |
| `settings/profile.tsx` | `PATCH /api/profile`, `DELETE /api/profile` (lewat `delete-user.tsx`) |
| `settings/appearance.tsx` | tidak ada API — murni local state (localStorage + cookie) |

`dashboard.tsx` tetap halaman statis (placeholder), tidak ada perubahan fungsional selain menghapus dependency Inertia.

### 4.6 Komponen shell (header, breadcrumbs, user menu)

- `components/app-header.tsx`, `breadcrumbs.tsx`, `user-menu-content.tsx`, `text-link.tsx`, `app-logo.tsx` ditulis ulang: `Link`/`usePage` dari `@inertiajs/react` → `Link`/`useLocation`/`useNavigate` dari `react-router-dom`, data user dari `useAuthStore()` bukan `usePage().props.auth`.
- **Sidebar collapsible** (`app-sidebar.tsx`, `app-sidebar-header.tsx`, `nav-main.tsx`, `nav-user.tsx`, `nav-footer.tsx`, `layouts/app/app-sidebar-layout.tsx`) **dihapus**, diganti dengan **header nav biasa** (`layouts/app/app-header-layout.tsx`). Sidebar lama bergantung pada shared prop Inertia `sidebarOpen` yang sudah tidak ada di dunia SPA murni; dropdown/mobile-sheet nav tetap dipertahankan lewat `app-header.tsx`.
- `hooks/use-current-url.ts` ditulis ulang dari `usePage()` (Inertia) ke `useLocation()` (`react-router-dom`).

### 4.7 Fitur yang di-drop sementara (2FA & Passkeys)

Sesuai keputusan penyederhanaan scope, file-file berikut **dihapus**:

- Pages: `pages/welcome.tsx`, `pages/auth/two-factor-challenge.tsx`, `pages/auth/confirm-password.tsx`, `pages/settings/security.tsx`
- Components: `manage-passkeys.tsx`, `manage-two-factor.tsx`, `passkey-item.tsx`, `passkey-register.tsx`, `passkey-verify.tsx`, `two-factor-recovery-codes.tsx`, `two-factor-setup-modal.tsx`
- Hooks: `use-two-factor-auth.ts`
- Layouts: `auth-card-layout.tsx`, `auth-split-layout.tsx` (sudah tidak dipakai halaman manapun)

`welcome.tsx` (landing page marketing) juga dihapus karena `/` langsung menjadi dashboard terproteksi — tidak ada kebutuhan landing page publik untuk sistem monitoring internal ini.

### 4.8 Wayfinder & Inertia route helpers

Folder `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/` **dihapus seluruhnya** — ini adalah kode auto-generated oleh Laravel Wayfinder yang membuat helper TypeScript ber-tipe untuk route Laravel (dipakai bareng Inertia `<Form {...store.form()}>`). Karena Inertia dan pola form-nya sudah tidak dipakai, seluruh mekanisme ini tidak relevan lagi; path/endpoint sekarang ditulis sebagai string biasa di tiap pemanggilan `axios`.

---

## 5. Konfigurasi build

### 5.1 `vite.config.ts`

Disederhanakan dari config `vite-plus` (custom wrapper dengan plugin wayfinder, babel react-compiler, font bunny, dsb.) menjadi config `vite` standar:

```ts
export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/main.tsx'], refresh: true }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
});
```

Entry point diganti dari `resources/js/app.tsx` ke `resources/js/main.tsx`.

### 5.2 `tsconfig.json` (baru)

Project ini sebelumnya **tidak punya `tsconfig.json` sama sekali** — resolusi alias `@/` & type-checking sebelumnya ditangani otomatis oleh tool `vite-plus` yang sudah dilepas. `tsconfig.json` dibuat eksplisit dengan `baseUrl` + `paths: {"@/*": ["resources/js/*"]}` agar alias `@/` tetap berfungsi untuk `tsc --noEmit` dan editor/IDE.

**Catatan teknis penting**: `types/global.d.ts` (augmentasi tipe untuk atribut HTML `passwordrules`) harus punya minimal satu `export {}` di file tersebut. Tanpa itu, `declare module 'react' { ... }` di file itu tidak dianggap sebagai *module augmentation*, melainkan **mendeklarasikan ulang seluruh module `react`**, sehingga semua named export bawaan React (`useState`, `useEffect`, `ComponentProps`, dst.) hilang dan seluruh project gagal type-check.

### 5.3 `package.json`

- Script `dev`/`build` diganti dari `vp dev`/`vp build` (binary `vite-plus`) ke `vite dev`/`vite build` standar.
- `devDependencies` yang sudah tidak dipakai dihapus: `vite-plus`, `@laravel/vite-plugin-wayfinder`, `@rolldown/plugin-babel`, `babel-plugin-react-compiler`.
- `dependencies` `@laravel/passkeys` (SDK WebAuthn frontend) dihapus karena fitur passkey di-drop.

---

## 6. Verifikasi yang sudah dilakukan

- `npx tsc --noEmit` → **0 error**.
- `npx vite build` → sukses (`public/build/manifest.json` + assets ter-generate).
- End-to-end via `curl` (server `php artisan serve` + `npm run dev` jalan bersamaan):
  1. `GET /sanctum/csrf-cookie` dengan header `Origin: http://localhost:5173` → `204`, cookie `XSRF-TOKEN` & `laravel-session` ter-set dengan `Access-Control-Allow-Origin`/`Access-Control-Allow-Credentials` yang benar.
  2. `POST /register` (dengan `X-XSRF-TOKEN` dari cookie) → `201 Created`.
  3. `GET /api/me` (dengan cookie sesi) → `200`, mengembalikan data user yang baru register.
  4. `POST /logout` → `204`.
  5. `GET /api/me` setelah logout / tanpa cookie sama sekali → `401` (keduanya).

---

## 7. Yang belum dikerjakan / di luar scope migrasi ini

- **Laravel Reverb / WebSocket** — sengaja tidak diimplementasikan (task terpisah setelah struktur SPA stabil, sesuai instruksi awal).
- **2FA & Passkeys** — backend (Fortify feature flag) dan seluruh UI-nya dinonaktifkan/dihapus. Untuk mengaktifkan kembali: re-enable `Features::twoFactorAuthentication()` / `Features::passkeys()` di `config/fortify.php`, lalu buat ulang endpoint JSON + halaman React untuk alur-alur tersebut.
- **Sidebar collapsible** — diganti header nav sederhana. Bisa dikembalikan sebagai peningkatan UI terpisah kalau dibutuhkan (misalnya saat jumlah menu bertambah untuk fitur monitoring parkir).
- **Route `/admin` contoh** — tidak dibuat karena tidak ada di codebase asli; tinggal tambah `<Route>` baru di `App.tsx` dengan pola `ProtectedRoute` yang sama.
- **Migration/model/seeder** — tidak disentuh sama sekali sesuai permintaan (migrasi ini murni layer routing–controller–frontend). Satu migration baru ditambahkan: `create_personal_access_tokens_table` (dari `laravel/sanctum`, dibutuhkan Sanctum untuk dukungan token API selain cookie).
