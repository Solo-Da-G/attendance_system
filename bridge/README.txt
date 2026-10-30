===============================================================
       ZKTECO CLOUD BRIDGE - ZERO-STRESS INSTRUCTIONS
===============================================================

This folder contains everything needed to bridge your physical
wall ZKTeco machine to your live Vercel Attendance system.

YOU DO NOT NEED:
  - NO GitHub Desktop
  - NO Git account login
  - NO MySQL database on this PC
  - NO XAMPP database creation or phpMyAdmin

ALL YOU NEED ON THIS OFFICE PC:
  1. This "bridge" folder copied to the PC (Desktop or C:\bridge).
  2. PHP (If XAMPP is installed, it already has PHP at C:\xampp\php\php.exe).

HOW TO RUN:
  1. Open "zk_bridge.php" in Notepad.
  2. Verify:
     - $cloud_url  = 'https://your-domain.vercel.app';
     - $device_ip  = '192.168.1.201'; (Your ZKTeco machine IP)
  3. Double click "sync.bat" to test the sync!

TO RUN AUTOMATICALLY EVERY 5 MINUTES:
  1. Open Windows Task Scheduler on this PC.
  2. Click "Create Basic Task" -> Name: "ZKTeco Sync".
  3. Trigger: Daily -> Repeat every 5 minutes.
  4. Action: Start a program -> Browse to this "sync.bat".
  5. Done!
===============================================================
