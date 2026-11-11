"""Dijalankan CI setelah `npx cap add android`: izin lokasi/foreground service, ikon aplikasi, nama."""
import os, re, subprocess, sys

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'android')
man = os.path.join(root, 'app/src/main/AndroidManifest.xml')
s = open(man, encoding='utf-8').read()
perms = [
    'android.permission.ACCESS_COARSE_LOCATION', 'android.permission.ACCESS_FINE_LOCATION',
    'android.permission.FOREGROUND_SERVICE', 'android.permission.FOREGROUND_SERVICE_LOCATION',
    'android.permission.POST_NOTIFICATIONS', 'android.permission.WAKE_LOCK',
]
add = ''.join('    <uses-permission android:name="%s" />\n' % p for p in perms if p not in s)
s = s.replace('</manifest>', add + '</manifest>')
open(man, 'w', encoding='utf-8').write(s)

# ikon dari logo aplikasi web
logo = os.path.join(os.path.dirname(root), '..', 'public', 'icons', 'icon-512.png')
res = os.path.join(root, 'app/src/main/res')
sizes = {'mdpi': 48, 'hdpi': 72, 'xhdpi': 96, 'xxhdpi': 144, 'xxxhdpi': 192}
for d, px in sizes.items():
    out = os.path.join(res, 'mipmap-' + d)
    os.makedirs(out, exist_ok=True)
    for name in ('ic_launcher.png', 'ic_launcher_round.png', 'ic_launcher_foreground.png'):
        subprocess.check_call(['convert', logo, '-resize', '%dx%d' % (px, px), os.path.join(out, name)])
# hapus ikon adaptif bawaan supaya PNG di atas dipakai
for rel in ('mipmap-anydpi-v26/ic_launcher.xml', 'mipmap-anydpi-v26/ic_launcher_round.xml', 'drawable-v24/ic_launcher_foreground.xml', 'drawable/ic_launcher_background.xml'):
    f = os.path.join(res, rel)
    if os.path.exists(f):
        os.remove(f)
print('patched')
