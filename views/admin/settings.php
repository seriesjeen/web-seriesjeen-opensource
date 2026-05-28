<?php
use App\Core\View;
use App\Core\Csrf;

View::layout('layouts/main');
View::title('ตั้งค่าข้อมูลเว็บไซต์ (Admin Website Settings)');
View::start('content');
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800/80 pb-6 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold font-heading bg-gradient-to-r from-white via-indigo-200 to-indigo-500 bg-clip-text text-transparent">
                ตั้งค่าข้อมูลเว็บไซต์
            </h1>
            <p class="text-slate-400 text-sm mt-1.5 font-medium">ปรับแต่งชื่อโลโก้เว็บไซต์และช่องทางติดต่อสื่อสารที่ส่วนท้ายหน้าหลัก</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-xs font-bold text-indigo-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                ฐานข้อมูลเชื่อมต่อปกติ
            </span>
        </div>
    </div>

    <!-- Admin Sub-Navigation / Tabs -->
    <div class="flex items-center gap-2 mb-8 bg-slate-900/40 p-1.5 rounded-2xl border border-slate-800/80 w-fit">
        <a href="/admin" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-400 hover:text-slate-200 transition-all duration-300 hover:bg-slate-800/40">
            <i class="fa-solid fa-users"></i>
            จัดการผู้ใช้ (User Management)
        </a>
        <a href="/admin/settings" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 bg-indigo-600 text-white shadow-lg shadow-indigo-500/25">
            <i class="fa-solid fa-sliders"></i>
            ตั้งค่าเว็บไซต์ (Website Settings)
        </a>
    </div>

    <!-- Main Settings Form -->
    <main class="glass-card p-8 rounded-2xl border border-slate-800/60 shadow-2xl relative">
        <h2 class="text-lg font-bold font-heading text-white mb-6 flex items-center gap-2 pb-4 border-b border-slate-850">
            <i class="fa-solid fa-sliders text-indigo-500 text-lg"></i>
            กำหนดค่าระบบหน้าบ้านหลัก
        </h2>
        <form action="/admin/settings/update" method="POST" class="space-y-6">
            <?= Csrf::field() ?>
            
            <!-- Name & Theme Colors Grid -->
            <div class="space-y-6 pb-6 border-b border-slate-850">
                <!-- Web Name -->
                <div>
                    <label for="web_name" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">ชื่อเว็บ / โลโก้แถบนำทาง (Web Name / Logo Label)</label>
                    <input
                        type="text"
                        id="web_name"
                        name="web_name"
                        value="<?= View::e($settings['web_name']['value'] ?? '') ?>"
                        required
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                        placeholder="เช่น SeriesJeen"
                    >
                    <p class="text-[10px] text-slate-550 mt-1.5 font-medium">ชื่อนี้จะปรากฏที่มุมบนซ้ายของแถบเมนูนำทางและหัวข้อแท็บบนสุดของเว็บบราวเซอร์</p>
                </div>

                <!-- Core Theme Colors Configuration -->
                <div class="pt-4 border-t border-slate-800/40">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-indigo-400 mb-4 flex items-center gap-1.5 font-heading">
                        <i class="fa-solid fa-palette text-indigo-500"></i> โทนสีและแสงนีออนหลัก (Theme Colors & Glowing Accents)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Theme Color -->
                        <div>
                            <label for="web_theme_color" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">สีหลักของเว็บไซต์ (Website Theme Color)</label>
                            <div class="flex gap-3">
                                <input
                                    type="color"
                                    id="web_theme_color_picker"
                                    value="<?= View::e($settings['web_theme_color']['value'] ?? '#6366f1') ?>"
                                    class="h-[46px] w-[60px] bg-slate-950/60 border border-slate-800/80 rounded-xl p-1 cursor-pointer outline-none transition"
                                    oninput="document.getElementById('web_theme_color').value = this.value; $('#web_theme_color').trigger('input');"
                                >
                                <input
                                    type="text"
                                    id="web_theme_color"
                                    name="web_theme_color"
                                    value="<?= View::e($settings['web_theme_color']['value'] ?? '#6366f1') ?>"
                                    required
                                    class="flex-1 bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-mono uppercase font-bold"
                                    placeholder="#6366f1"
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) { document.getElementById('web_theme_color_picker').value = this.value; }"
                                >
                            </div>
                            <p class="text-[10px] text-slate-550 mt-1.5 font-medium">รหัสสี Hex เพื่อกำหนดโทนสีหลักและแสงนีออนเรืองแสงของปุ่มและขอบระบบทั้งหมด</p>
                        </div>

                        <!-- Gradient Color -->
                        <div>
                            <label for="web_gradient_color" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">สีไล่ระดับ / เฉดสีเน้น (Gradient / Accent Color)</label>
                            <div class="flex gap-3">
                                <input
                                    type="color"
                                    id="web_gradient_color_picker"
                                    value="<?= View::e($settings['web_gradient_color']['value'] ?? '#a855f7') ?>"
                                    class="h-[46px] w-[60px] bg-slate-950/60 border border-slate-800/80 rounded-xl p-1 cursor-pointer outline-none transition"
                                    oninput="document.getElementById('web_gradient_color').value = this.value; $('#web_gradient_color').trigger('input');"
                                >
                                <input
                                    type="text"
                                    id="web_gradient_color"
                                    name="web_gradient_color"
                                    value="<?= View::e($settings['web_gradient_color']['value'] ?? '#a855f7') ?>"
                                    required
                                    class="flex-1 bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-mono uppercase font-bold"
                                    placeholder="#a855f7"
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) { document.getElementById('web_gradient_color_picker').value = this.value; }"
                                >
                            </div>
                            <p class="text-[10px] text-slate-550 mt-1.5 font-medium">รหัสสีคู่ขนานสำหรับการไล่เฉดสีปุ่ม, ตัวหนังสือไล่ระดับ และเอฟเฟกต์โฮเวอร์ (Hover)</p>
                        </div>
                    </div>
                </div>

                <!-- Page Layout Colors Configuration -->
                <div class="pt-4 border-t border-slate-800/40">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-indigo-400 mb-4 flex items-center gap-1.5 font-heading">
                        <i class="fa-solid fa-window-restore text-indigo-500"></i> สีของเลย์เอาต์และพื้นหลัง (Layout & Background Colors)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Navbar BG Color -->
                        <div>
                            <label for="web_navbar_color" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">สีแถบนำทาง (Navbar BG Color)</label>
                            <div class="flex gap-3">
                                <input
                                    type="color"
                                    id="web_navbar_color_picker"
                                    value="<?= View::e($settings['web_navbar_color']['value'] ?? '#080c1c') ?>"
                                    class="h-[46px] w-[60px] bg-slate-950/60 border border-slate-800/80 rounded-xl p-1 cursor-pointer outline-none transition"
                                    oninput="document.getElementById('web_navbar_color').value = this.value; $('#web_navbar_color').trigger('input');"
                                >
                                <input
                                    type="text"
                                    id="web_navbar_color"
                                    name="web_navbar_color"
                                    value="<?= View::e($settings['web_navbar_color']['value'] ?? '#080c1c') ?>"
                                    required
                                    class="flex-1 bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-3 text-xs text-slate-200 transition font-mono uppercase font-bold"
                                    placeholder="#080c1c"
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) { document.getElementById('web_navbar_color_picker').value = this.value; }"
                                >
                            </div>
                            <p class="text-[10px] text-slate-550 mt-1.5 font-medium">สีพื้นหลังเมนูด้านบน จะถูกผสมความโปร่งใสกระจกเบลอ 75% โดยอัตโนมัติ</p>
                        </div>

                        <!-- Footer BG Color -->
                        <div>
                            <label for="web_footer_color" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">สีส่วนท้าย (Footer BG Color)</label>
                            <div class="flex gap-3">
                                <input
                                    type="color"
                                    id="web_footer_color_picker"
                                    value="<?= View::e($settings['web_footer_color']['value'] ?? '#060814') ?>"
                                    class="h-[46px] w-[60px] bg-slate-950/60 border border-slate-800/80 rounded-xl p-1 cursor-pointer outline-none transition"
                                    oninput="document.getElementById('web_footer_color').value = this.value; $('#web_footer_color').trigger('input');"
                                >
                                <input
                                    type="text"
                                    id="web_footer_color"
                                    name="web_footer_color"
                                    value="<?= View::e($settings['web_footer_color']['value'] ?? '#060814') ?>"
                                    required
                                    class="flex-1 bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-3 text-xs text-slate-200 transition font-mono uppercase font-bold"
                                    placeholder="#060814"
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) { document.getElementById('web_footer_color_picker').value = this.value; }"
                                >
                            </div>
                            <p class="text-[10px] text-slate-550 mt-1.5 font-medium">สีพื้นหลังของแผง Footer ส่วนล่างสุดที่เป็นที่เก็บช่องทางติดต่อ</p>
                        </div>

                        <!-- Main BG Color -->
                        <div>
                            <label for="web_bg_color" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">สีพื้นหลังหลัก (Main BG Color)</label>
                            <div class="flex gap-3">
                                <input
                                    type="color"
                                    id="web_bg_color_picker"
                                    value="<?= View::e($settings['web_bg_color']['value'] ?? '#060814') ?>"
                                    class="h-[46px] w-[60px] bg-slate-950/60 border border-slate-800/80 rounded-xl p-1 cursor-pointer outline-none transition"
                                    oninput="document.getElementById('web_bg_color').value = this.value; $('#web_bg_color').trigger('input');"
                                >
                                <input
                                    type="text"
                                    id="web_bg_color"
                                    name="web_bg_color"
                                    value="<?= View::e($settings['web_bg_color']['value'] ?? '#060814') ?>"
                                    required
                                    class="flex-1 bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-3 text-xs text-slate-200 transition font-mono uppercase font-bold"
                                    placeholder="#060814"
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) { document.getElementById('web_bg_color_picker').value = this.value; }"
                                >
                            </div>
                            <p class="text-[10px] text-slate-550 mt-1.5 font-medium">สีพื้นหลังร่างกายหลักของทุกหน้าเพจเว็บรวมถึงฉากหลังของเนื้อหาซีรีส์</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Logo Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-850">
                <!-- Logo Image URL -->
                <div>
                    <label for="web_logo_url" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">ลิงก์รูปภาพโลโก้ (Logo Image URL)</label>
                    <input
                        type="text"
                        id="web_logo_url"
                        name="web_logo_url"
                        value="<?= View::e($settings['web_logo_url']['value'] ?? '') ?>"
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                        placeholder="เช่น https://domain.com/logo.png หรือ /public/logo.png"
                    >
                    <p class="text-[10px] text-slate-550 mt-1.5 font-medium">ระบุ URL รูปโลโก้ (เช่น PNG, SVG, JPG) หากเว้นว่างไว้จะแสดงผลเป็นไอคอน 🎬 แทนโดยอัตโนมัติ</p>
                </div>

                <!-- Logo Width Size -->
                <div>
                    <label for="web_logo_width" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">ความกว้างโลโก้ (Logo Display Width - Pixels)</label>
                    <input
                        type="number"
                        id="web_logo_width"
                        name="web_logo_width"
                        value="<?= View::e($settings['web_logo_width']['value'] ?? '32') ?>"
                        min="10"
                        max="300"
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                        placeholder="32"
                    >
                    <p class="text-[10px] text-slate-550 mt-1.5 font-medium">กำหนดความกว้างของโลโก้หน่วยเป็นพิกเซล (แนะนำระหว่าง 24px - 150px)</p>
                </div>
            </div>

            <!-- Footer Section Header -->
            <div class="pt-4 border-t border-slate-850">
                <label class="block text-xs font-extrabold text-slate-350 mb-4 uppercase tracking-wider">ช่องทางการติดต่อส่วนล่างของหน้าเว็บ (Footer Social Links)</label>
                
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Line Contact -->
                    <div class="space-y-2 bg-slate-950/20 p-4 rounded-xl border border-slate-850 flex flex-col justify-between">
                        <div>
                            <label for="contact_line" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">🟢 LINE: LINE ID หรือ ลิงก์แอดไลน์</label>
                            <input
                                type="text"
                                id="contact_line"
                                name="contact_line"
                                value="<?= View::e($settings['contact_line']['value'] ?? '') ?>"
                                class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-2 text-xs text-slate-300 transition"
                                placeholder="เช่น @seriesjeen หรือ https://line.me/..."
                            >
                        </div>
                        <div class="flex items-center pt-2.5">
                            <input
                                type="checkbox"
                                id="line_visible"
                                name="line_visible"
                                value="1"
                                <?= !empty($settings['contact_line']['is_visible']) ? 'checked' : '' ?>
                                class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-brand-500 w-3.5 h-3.5"
                            >
                            <label for="line_visible" class="text-[10px] text-slate-400 font-bold ml-1.5 cursor-pointer">เปิดแสดงผลปุ่ม Line</label>
                        </div>
                    </div>

                    <!-- Facebook Contact -->
                    <div class="space-y-2 bg-slate-950/20 p-4 rounded-xl border border-slate-850 flex flex-col justify-between">
                        <div>
                            <label for="contact_facebook" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">🔵 Facebook: ลิงก์เพจเฟสบุ๊ค</label>
                            <input
                                type="text"
                                id="contact_facebook"
                                name="contact_facebook"
                                value="<?= View::e($settings['contact_facebook']['value'] ?? '') ?>"
                                class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-2 text-xs text-slate-300 transition"
                                placeholder="เช่น https://facebook.com/page..."
                            >
                        </div>
                        <div class="flex items-center pt-2.5">
                            <input
                                type="checkbox"
                                id="facebook_visible"
                                name="facebook_visible"
                                value="1"
                                <?= !empty($settings['contact_facebook']['is_visible']) ? 'checked' : '' ?>
                                class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-brand-500 w-3.5 h-3.5"
                            >
                            <label for="facebook_visible" class="text-[10px] text-slate-400 font-bold ml-1.5 cursor-pointer">เปิดแสดงผลปุ่ม Facebook</label>
                        </div>
                    </div>

                    <!-- Other Contact -->
                    <div class="space-y-2 bg-slate-950/20 p-4 rounded-xl border border-slate-850 flex flex-col justify-between">
                        <div>
                            <label for="contact_other" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">🌐 อื่น ๆ: เทเลแกรม / ลิงก์ช่อง</label>
                            <input
                                type="text"
                                id="contact_other"
                                name="contact_other"
                                value="<?= View::e($settings['contact_other']['value'] ?? '') ?>"
                                class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-3 py-2 text-xs text-slate-300 transition"
                                placeholder="เช่น https://t.me/group..."
                            >
                        </div>
                        <div class="flex items-center pt-2.5">
                            <input
                                type="checkbox"
                                id="other_visible"
                                name="other_visible"
                                value="1"
                                <?= !empty($settings['contact_other']['is_visible']) ? 'checked' : '' ?>
                                class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-brand-500 w-3.5 h-3.5"
                            >
                            <label for="other_visible" class="text-[10px] text-slate-400 font-bold ml-1.5 cursor-pointer">เปิดแสดงผลปุ่มช่องทางอื่น</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Customized Domain & Link Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-850">
                <!-- Footer Text -->
                <div>
                    <label for="web_footer_text" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">ข้อความลิงก์ส่วน Footer (Footer Link Text)</label>
                    <input
                        type="text"
                        id="web_footer_text"
                        name="web_footer_text"
                        value="<?= View::e($settings['web_footer_text']['value'] ?? 'api.seriesjeen.online') ?>"
                        required
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                        placeholder="เช่น api.seriesjeen.online"
                    >
                    <p class="text-[10px] text-slate-550 mt-1.5 font-medium">ข้อความชื่อเว็บไซต์/โดเมนที่จะปรากฏบน Footer ลิงก์</p>
                </div>

                <!-- Footer URL -->
                <div>
                    <label for="web_footer_url" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">ลิงก์ปลายทางส่วน Footer (Footer Link URL)</label>
                    <input
                        type="text"
                        id="web_footer_url"
                        name="web_footer_url"
                        value="<?= View::e($settings['web_footer_url']['value'] ?? 'https://api.seriesjeen.online') ?>"
                        required
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                        placeholder="เช่น https://api.seriesjeen.online"
                    >
                    <p class="text-[10px] text-slate-550 mt-1.5 font-medium">ลิงก์ URL ปลายทางเมื่อมีคนกดที่ Footer ลิงก์</p>
                </div>
            </div>

            <!-- Login Page Description Setting -->
            <div class="pt-4 border-t border-slate-850">
                <label for="web_login_description" class="block text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wide">คำอธิบายหน้าเข้าสู่ระบบ (Login Page Description)</label>
                <textarea
                    id="web_login_description"
                    name="web_login_description"
                    rows="3"
                    class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm text-slate-200 transition font-medium"
                    placeholder="ป้อนคำอธิบายของระบบล็อกอินสำหรับแสดงที่ส่วนท้ายหน้าล็อกอิน..."
                ><?= View::e($settings['web_login_description']['value'] ?? '') ?></textarea>
                <p class="text-[10px] text-slate-550 mt-1.5 font-medium">ข้อความชี้แจงความปลอดภัยหรือคำอธิบายเพิ่มเติมสำหรับผู้ใช้ที่จะปรากฏที่ส่วนล่างสุดของแบบฟอร์มล็อกอิน (สามารถเคาะขึ้นบรรทัดใหม่ได้)</p>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl py-3 text-sm font-bold shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all duration-300 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-floppy-disk mr-1"></i>
                บันทึกการตั้งค่าเว็บไซต์ทั้งหมด
            </button>
        </form>

        <!-- Live Preview Card -->
        <div class="p-6 rounded-2xl border border-indigo-500/20 bg-indigo-950/5 mt-8 shadow-xl">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-indigo-400 mb-4 flex items-center gap-1.5 font-heading">
                <i class="fa-solid fa-eye text-indigo-500"></i> Live Preview (ตัวอย่างการแสดงผลจริงบนหน้าเว็บแบบจำลองย่อส่วน)
            </h3>
            <div class="rounded-xl border border-slate-800/85 overflow-hidden shadow-xl" id="live-preview-box" style="background-color: #060814;">
                <!-- Mini Navbar -->
                <div id="preview-navbar" class="px-4 py-3 border-b border-slate-800/80 flex items-center justify-between transition-all duration-300" style="background-color: rgba(8, 12, 28, 0.75); backdrop-filter: blur(8px);">
                    <div class="flex items-center gap-2" id="preview-logo-wrapper">
                        <!-- Dynamic logo and title -->
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full" id="preview-nav-dot" style="background-color: #6366f1;"></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">หน้าแรก</span>
                    </div>
                </div>
                
                <!-- Mini Content Area -->
                <div class="p-6 text-center space-y-3 relative overflow-hidden" id="preview-content-area" style="background-color: #060814;">
                    <!-- Dynamic glow blobs -->
                    <div class="absolute -top-10 -left-10 w-24 h-24 rounded-full blur-xl pointer-events-none transition-all duration-500" id="preview-blob-1" style="background-color: rgba(99, 102, 241, 0.08);"></div>
                    <div class="absolute -bottom-10 -right-10 w-24 h-24 rounded-full blur-xl pointer-events-none transition-all duration-500" id="preview-blob-2" style="background-color: rgba(168, 85, 247, 0.08);"></div>
                    
                    <p class="text-[11px] text-slate-405 font-medium max-w-sm mx-auto">ระบบสตรีมมิ่งซีรีส์พร้อมความละเอียดสูง ลื่นไหล ไม่มีโฆษณาคั่น</p>
                    <button id="preview-btn" class="px-4 py-1.5 rounded-lg text-[10px] font-bold text-white shadow-lg transition-all duration-300 active:scale-95" style="background: linear-gradient(135deg, #6366f1, #a855f7);">
                        รับชมซีรีส์ฟรี
                    </button>
                </div>
                
                <!-- Mini Footer -->
                <div id="preview-footer" class="px-4 py-3 border-t border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-slate-500 transition-all duration-300" style="background-color: #060814;">
                    <div class="text-[10px] font-medium">
                        <span id="preview-footer-name">SeriesJeen</span> Player · <span id="preview-footer-link" class="transition" style="color: #6366f1;">api.seriesjeen.online</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2" id="preview-contact-container">
                        <!-- Preview social buttons rendered dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php View::start('scripts'); ?>
<script>
$(document).ready(function() {
    // Intercept flashes and display with beautiful SweetAlert2 notifications
    <?php if (!empty($flash_success)): ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: <?= json_encode($flash_success) ?>,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        background: '#090d16',
        color: '#cbd5e1'
    });
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด!',
        text: <?= json_encode($flash_error) ?>,
        confirmButtonColor: '#4f46e5',
        background: '#090d16',
        color: '#cbd5e1'
    });
    <?php endif; ?>

    // Live Settings Preview binding
    function updatePreview() {
        const webName = $('#web_name').val() || 'SeriesJeen';
        const logoUrl = $('#web_logo_url').val() || '';
        let logoWidth = parseInt($('#web_logo_width').val()) || 32;
        const footerText = $('#web_footer_text').val() || 'api.seriesjeen.online';
        
        const themeColor = $('#web_theme_color').val() || '#6366f1';
        const gradientColor = $('#web_gradient_color').val() || '#a855f7';
        const navbarColor = $('#web_navbar_color').val() || '#080c1c';
        const footerColor = $('#web_footer_color').val() || '#060814';
        const bgColor = $('#web_bg_color').val() || '#060814';

        if (logoWidth < 10) logoWidth = 10;
        if (logoWidth > 300) logoWidth = 300;

        $('#preview-footer-name').text(webName);
        $('#preview-footer-link').text(footerText);

        // Update colors dynamically in preview
        if (/^#[0-9a-fA-F]{6}$/.test(bgColor)) {
            $('#live-preview-box').css('background-color', bgColor);
            $('#preview-content-area').css('background-color', bgColor);
        }
        if (/^#[0-9a-fA-F]{6}$/.test(navbarColor)) {
            $('#preview-navbar').css('background-color', navbarColor + 'BF'); // BF is 75% opacity in hex
        }
        if (/^#[0-9a-fA-F]{6}$/.test(footerColor)) {
            $('#preview-footer').css('background-color', footerColor);
        }
        if (/^#[0-9a-fA-F]{6}$/.test(themeColor)) {
            $('#preview-nav-dot').css('background-color', themeColor);
            $('#preview-footer-link').css('color', themeColor);
            $('#preview-blob-1').css('background-color', themeColor);
        }
        if (/^#[0-9a-fA-F]{6}$/.test(gradientColor)) {
            $('#preview-blob-2').css('background-color', gradientColor);
        }
        if (/^#[0-9a-fA-F]{6}$/.test(themeColor) && /^#[0-9a-fA-F]{6}$/.test(gradientColor)) {
            $('#preview-btn').css('background', `linear-gradient(135deg, ${themeColor}, ${gradientColor})`);
            $('#preview-btn').css('box-shadow', `0 4px 12px ${themeColor}40`);
        }

        // Update logo preview wrapper
        const logoWrapper = $('#preview-logo-wrapper');
        logoWrapper.empty();
        
        if (logoUrl !== '') {
            // Build via jQuery attr/css (not string interpolation) so a crafted logo URL
            // or web name can't break out of the markup and inject script.
            $('<img>', { class: 'object-contain rounded', alt: 'Logo' })
                .attr('src', logoUrl)
                .css({ width: logoWidth + 'px', height: 'auto' })
                .appendTo(logoWrapper);
        } else {
            logoWrapper.append('<span class="text-lg">🎬</span>');
        }
        $('<span>', { class: 'font-bold text-white tracking-tight', text: webName }).appendTo(logoWrapper);

        // Update footer social preview tags
        const container = $('#preview-contact-container');
        container.empty();

        const lineVal = $('#contact_line').val();
        const lineShow = $('#line_visible').is(':checked');
        const fbVal = $('#contact_facebook').val();
        const fbShow = $('#facebook_visible').is(':checked');
        const othVal = $('#contact_other').val();
        const othShow = $('#other_visible').is(':checked');

        let hasContacts = false;

        if (lineShow && lineVal !== '') {
            $('<span>', {
                class: 'inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold text-[9px] tracking-wide',
                text: '🟢 LINE: ' + lineVal
            }).appendTo(container);
            hasContacts = true;
        }

        if (fbShow && fbVal !== '') {
            const fb = $('<span>', {
                class: 'inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold text-[9px] tracking-wide',
                text: '🔵 Facebook'
            });
            if (/^#[0-9a-fA-F]{6}$/.test(themeColor)) {
                fb.css({ 'background-color': themeColor + '1A', 'border-color': themeColor + '33', color: themeColor });
            }
            container.append(fb);
            hasContacts = true;
        }

        if (othShow && othVal !== '') {
            container.append(`
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-350 font-bold text-[9px] tracking-wide">
                    🌐 ช่องทางอื่น ๆ
                </span>
            `);
            hasContacts = true;
        }

        if (!hasContacts) {
            container.append(`<span class="text-[10px] text-slate-650 italic">ไม่ได้เปิดเผยช่องทางติดต่อ</span>`);
        }
    }

    // Trigger update preview on input changes
    $('#web_name, #web_logo_url, #web_logo_width, #contact_line, #contact_facebook, #contact_other, #web_footer_text, #web_footer_url, #web_theme_color, #web_gradient_color, #web_navbar_color, #web_footer_color, #web_bg_color').on('input', updatePreview);
    $('#line_visible, #facebook_visible, #other_visible').on('change', updatePreview);

    // Initial run
    updatePreview();
});
</script>
<?php View::stop(); ?>
