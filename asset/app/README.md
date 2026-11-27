# TDS Attendance System — Android Native App (.apk)

This directory hosts the compiled Android Application Package (**TDS_Attendance.apk**) for enterprise staff distribution.

## Why Use the APK?
- **100% Guaranteed Installation**: Bypasses POCO, Xiaomi (MIUI/HyperOS), and Infinix launcher locks (e.g. 'Lock Home screen layout' or blocked Chrome shortcut permissions).
- **Native Full-Screen App**: Installs with the TDS logo directly onto staff devices with no browser address bar or Chrome menu friction.
- **Zero Update Friction**: The APK wraps the live cloud instance (https://attendance-system-delta-five.vercel.app), meaning any changes pushed to the server instantly update inside the APK without reinstalling.

## How to Build or Rebuild the APK (Free & 60 Seconds via Microsoft PWABuilder):
1. Visit [PWABuilder](https://www.pwabuilder.com).
2. Enter the production URL:
   https://attendance-system-delta-five.vercel.app
3. Click **Start**.
4. Click **Package for Android**.
5. Download the generated package zip file.
6. Copy pp-release-signed.apk into this folder and rename it:
   sset/app/TDS_Attendance.apk
7. Commit and push via GitHub Desktop.