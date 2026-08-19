# RunIX — Mobile Build Instructions

RunIX is wrapped as a native iOS/Android shell via [Capacitor](https://capacitorjs.com). The native apps don't bundle the UI — they load it live from `server.url` in `capacitor.config.json`, exactly like a browser would. Native code only comes into play for the three plugins installed: Camera, Geolocation, and Push Notifications.

## 0. Current config is dev-only — read this first

```json
"server": { "url": "http://localhost:8000", "cleartext": true }
```

This points at your local `php artisan serve`, over plain HTTP. It's fine for local testing on a simulator, but two things to know before you rely on it:

- **`localhost` means different things on different targets.** The iOS Simulator shares your Mac's network stack, so `http://localhost:8000` works there once `php artisan serve` is running. The **Android emulator does not** — `localhost` inside the emulator refers to the emulator itself, not your machine. Use `http://10.0.2.2:8000` instead when testing on the Android emulator. A **physical device** (either platform) can't reach your machine's `localhost` at all — it needs your machine's real LAN IP (e.g. `http://192.168.1.x:8000`, both devices on the same Wi-Fi) or a tunnel (ngrok/Cloudflare Tunnel).
- **Before shipping anywhere real** (TestFlight, Play internal testing, App Store, Play Store), replace this with your real HTTPS domain and set `cleartext: false`. App Store/Play Store review will reject a cleartext-HTTP production app. Whenever you change it:
  ```bash
  npx cap sync
  ```
  Capacitor bakes `server.url` into the native project's own config at sync time — editing the JSON alone doesn't update an already-built app.

## 1. Running RunIX itself while testing the mobile shell

The mobile app is just a window onto your running Laravel app — it needs the same backend RunIX always needs:

```bash
composer run dev
```

This starts `php artisan serve`, the queue worker, and Vite together (see `composer.json`). For the **live driver-location/dispatch-board/order-tracking features to actually update in real time** on the mobile app, Reverb needs to be running too:

```bash
php artisan reverb:start
```

Without Reverb running, RunIX still works on mobile (polling fallback everywhere covers it — see `docs/ARCHITECTURE.md`), it just won't feel "live."

## 2. iOS — Xcode setup (requires a Mac)

1. Install [Xcode](https://apps.apple.com/app/xcode/id497799835) from the Mac App Store — this cannot be done on Linux/WSL, Xcode is macOS-only.
2. Open the project:
   ```bash
   npx cap open ios
   ```
   Opens `ios/App/App.xcodeproj`.
3. Select the **App** target → **Signing & Capabilities** → choose your Apple Developer Team. The build fails with a code-signing error until you do this.
4. Pick a simulator (e.g. "iPhone 15") or a connected device from the scheme selector, then press ▶️.
5. **Push Notifications**: to actually use `@capacitor/push-notifications`, add the **Push Notifications** capability and enable **Background Modes → Remote notifications** under Signing & Capabilities. Not enabled automatically.
6. **Location for the driver app specifically**: RunIX's driver-side location sharing (`resources/js/runix/driver-location.js`) uses the browser Geolocation API through the WebView, which the `NSLocationWhenInUseUsageDescription` string already added to `Info.plist` covers. If you later want location updates to keep flowing while the app is backgrounded (a driver's phone locked in their pocket), that needs the native `@capacitor/geolocation` plugin wired in specifically for background tracking, plus `NSLocationAlwaysAndWhenInUseUsageDescription` and the **Location Updates** background mode — neither is set up yet, since that's a real product decision (battery/privacy trade-offs), not just a build step.

`ios/App/App/Info.plist` already has the three usage-description strings from your spec (`NSCameraUsageDescription`, `NSPhotoLibraryUsageDescription`, `NSLocationWhenInUseUsageDescription`) — without these, iOS kills the app instantly the moment a plugin requests that permission.

## 3. Android — Android Studio setup

1. Install [Android Studio](https://developer.android.com/studio).
2. Open the project:
   ```bash
   npx cap open android
   ```
   Opens the `android/` folder as a Gradle project. First open triggers a Gradle sync (downloads SDK/build tools) — let it finish.
3. Pick an emulator from **Device Manager** or connect a physical device with USB debugging enabled.
4. Press ▶️.
5. Remember the `10.0.2.2` vs `localhost` distinction from §0 if you're testing against your local dev server.

Camera/Geolocation/Push permissions are declared inside each plugin's own manifest and merged into the app automatically by Gradle at build time — `android/app/src/main/AndroidManifest.xml` only lists `INTERNET` in the raw source, that's expected; the merged manifest (visible after a build) includes the rest.

For push notifications on Android you additionally need a Firebase project with `google-services.json` in `android/app/` — not set up here, since it requires your own Firebase credentials.

## 4. Testing on emulator/device

```bash
# iOS (Mac only)
npx cap run ios

# Android
npx cap run android
```

Both build and launch on whichever simulator/emulator or connected device you pick interactively.

**For GPS accuracy testing specifically** (relevant to RunIX's driver tracking): simulators/emulators let you fake a location, but real movement/accuracy behavior only shows up on a physical device outdoors. Test the driver-location flow on an actual phone before trusting it.

## 5. After changing anything web-facing

```bash
npx cap sync
```

Re-copies `public/` into both native projects and re-applies the config. Not needed for backend-only PHP changes that don't touch `public/` — those are served live from your domain/dev server either way.

## 6. Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Blank white screen on launch | `server.url` unreachable from that target — see the `localhost` vs `10.0.2.2` vs LAN-IP distinction in §0. Check the URL loads in a regular mobile browser on the same device/emulator first. |
| Works on iOS Simulator, not Android emulator | Classic `localhost` vs `10.0.2.2` mismatch (§0). |
| iOS build fails with a signing error | No Apple Developer Team selected (§2.3). A free Apple ID is enough for simulator/local-device testing; App Store submission needs a paid Developer Program membership. |
| "Cleartext HTTP traffic not permitted" | You changed `server.url` to `https://` but left `cleartext: true`, or vice-versa mismatch. Set `cleartext: false` once you're on real HTTPS. |
| Camera/Location prompt never appears, app just closes | Missing usage-description string — already added for the three current plugins (§0/§2); add more if you install additional plugins later. |
| Driver location doesn't update live on mobile | Reverb isn't running (§1) — the app still works via polling fallback, just not instantly. |
| Changes to Blade views don't show up in the app | Forgot `npx cap sync` after a change touching `public/`, or the device has a cached build — force-quit and relaunch. |
| `npx cap open ios` does nothing / errors | You're not on macOS — iOS development is only possible on a Mac. |
| Gradle sync fails in Android Studio | Usually a JDK/SDK version mismatch on first setup — let Android Studio's own "Fix" prompts run. |
| Push notifications never arrive | Android needs `google-services.json` from your own Firebase project; iOS needs the Push Notifications capability + a provisioning profile with that entitlement (§2.5). Neither is configured yet — this only scaffolds the plugin. |

## 7. What's already done vs. what's still yours to do

**Done** (this setup pass):
- Capacitor core/CLI + `@capacitor/camera`, `@capacitor/geolocation`, `@capacitor/push-notifications` installed
- `ios/` and `android/` native projects generated and synced
- iOS `Info.plist` usage-description strings for Camera/Photo Library/Location added
- `capacitor.config.json` pointed at your local dev server (`http://localhost:8000`, cleartext on)

**Still yours to do**:
- Deploy RunIX to a real domain, then update `server.url` to that HTTPS URL and set `cleartext: false`, then `npx cap sync`
- Apple Developer Team + signing (iOS)
- Firebase project + `google-services.json` (Android push) and Push Notifications capability (iOS push), if you want push to actually work
- Decide on background location tracking for drivers if needed (§2.6) — a product decision, not a build step
- App icons / splash screens (Capacitor ships generic placeholders — see [Capacitor Assets](https://capacitorjs.com/docs/guides/splash-screens-and-icons))
- App Store Connect / Google Play Console listings and submission
