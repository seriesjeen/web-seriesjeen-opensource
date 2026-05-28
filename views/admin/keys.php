<?php
use App\Core\View;
use App\Core\Csrf;

View::layout('layouts/main');
View::title('จัดการผู้ใช้งานหลังบ้าน (Admin User Management)');
View::start('content');

$parseUa = function(?string $ua) {
    if (empty($ua)) return 'ยังไม่ระบุอุปกรณ์';
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';

    if (preg_match('/iphone|ipad|ipod/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/windows|win32/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/linux/i', $ua)) {
        $os = 'Linux';
    }

    if (preg_match('/chrome|crios/i', $ua) && !preg_match('/opr|opios|edge|edg/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios|android/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/firefox|fxios/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/edge|edg/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/opera|opr|opios/i', $ua)) {
        $browser = 'Opera';
    }

    return "$browser on $os";
};
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800/80 pb-6 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold font-heading bg-gradient-to-r from-white via-indigo-200 to-indigo-500 bg-clip-text text-transparent">
                ระบบควบคุมผู้ใช้หลังบ้าน
            </h1>
            <p class="text-slate-400 text-sm mt-1.5 font-medium">จัดการรหัสเข้าใช้งาน สิทธิ์ของสมาชิก และควบคุมสถิติโดยรวม</p>
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
        <a href="/admin" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 bg-indigo-600 text-white shadow-lg shadow-indigo-500/25">
            <i class="fa-solid fa-users"></i>
            จัดการผู้ใช้ (User Management)
        </a>
        <a href="/admin/settings" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-400 hover:text-slate-200 transition-all duration-300 hover:bg-slate-800/40">
            <i class="fa-solid fa-sliders"></i>
            ตั้งค่าเว็บไซต์ (Website Settings)
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="glass-card p-5 rounded-2xl border border-slate-800/60 shadow-xl relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 text-slate-800/20 text-7xl font-bold group-hover:scale-110 transition duration-500 pointer-events-none">🔑</div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">รหัสทั้งหมด</div>
            <div class="text-3xl font-extrabold text-white mt-2 font-heading"><?= $stats['total'] ?></div>
        </div>
        <div class="glass-card p-5 rounded-2xl border border-slate-800/60 shadow-xl relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 text-emerald-800/10 text-7xl font-bold group-hover:scale-110 transition duration-500 pointer-events-none">👤</div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">ผู้ใช้ทั่วไป (Active)</div>
            <div class="text-3xl font-extrabold text-emerald-400 mt-2 font-heading"><?= $stats['active_user'] ?></div>
        </div>
        <div class="glass-card p-5 rounded-2xl border border-slate-800/60 shadow-xl relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 text-indigo-800/10 text-7xl font-bold group-hover:scale-110 transition duration-500 pointer-events-none">🛡️</div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">ผู้ดูแลระบบ (Admin)</div>
            <div class="text-3xl font-extrabold text-indigo-400 mt-2 font-heading"><?= $stats['admin'] ?></div>
        </div>
        <div class="glass-card p-5 rounded-2xl border border-slate-800/60 shadow-xl relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 text-red-800/10 text-7xl font-bold group-hover:scale-110 transition duration-500 pointer-events-none">⏳</div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">รหัสหมดอายุ</div>
            <div class="text-3xl font-extrabold text-red-400 mt-2 font-heading"><?= $stats['expired_user'] ?></div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid lg:grid-cols-[360px_1fr] gap-8 items-start">
        <!-- Generate Key Form -->
        <aside class="glass-card p-6 rounded-2xl border border-slate-800/60 shadow-2xl relative">
            <h2 class="text-lg font-bold font-heading text-white mb-5 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-indigo-500 text-lg"></i>
                สร้างรหัสเข้าใช้งานใหม่
            </h2>
            <form action="/admin/keys/create" method="POST" class="space-y-4">
                <?= Csrf::field() ?>
                <div>
                    <label for="role" class="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wide">สิทธิ์ผู้ใช้งาน (Role)</label>
                    <select id="role" name="role" class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-2.5 text-sm text-slate-200 transition" onchange="toggleDuration(this.value)">
                        <option value="user">ผู้ใช้ทั่วไป (User)</option>
                        <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    </select>
                </div>

                <div id="duration-wrapper">
                    <label for="duration" class="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wide">ระยะเวลาใช้งาน (Duration)</label>
                    <select id="duration" name="duration" class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-2.5 text-sm text-slate-200 transition" onchange="toggleDurationField(this.value)">
                        <option value="1h">1 ชั่วโมง</option>
                        <option value="1d">1 วัน</option>
                        <option value="7d" selected>7 วัน</option>
                        <option value="30d">30 วัน</option>
                        <option value="365d">365 วัน</option>
                        <option value="custom">กำหนดเอง (Custom Date)</option>
                        <option value="unlimited">ไม่มีวันหมดอายุ (ไม่มีลิมิต)</option>
                    </select>
                </div>

                <div id="custom-expiry-wrapper" style="display: none;" class="mt-4">
                    <label for="custom_expiry" class="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wide">เลือกวันและเวลาหมดอายุ</label>
                    <input
                        type="datetime-local"
                        id="custom_expiry"
                        name="custom_expiry"
                        class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-2.5 text-sm text-slate-200 transition"
                    >
                </div>

                <button type="submit" class="w-full mt-2 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl py-3 text-sm font-bold shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all duration-300 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-key mr-1"></i>
                    สร้างรหัสใหม่
                </button>
            </form>
        </aside>

        <!-- Keys Table Card -->
        <main class="glass-card rounded-2xl border border-slate-800/60 shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800/60 bg-slate-900/10 flex items-center justify-between">
                <h3 class="text-sm font-bold tracking-wider uppercase text-slate-350 flex items-center gap-2">
                    <i class="fa-solid fa-table-list text-indigo-500 text-lg"></i>
                    รายการรหัสทั้งหมดในระบบ
                </h3>
            </div>
            
            <div class="overflow-x-auto p-6">
                <table id="keysTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-slate-400 text-xs uppercase font-bold tracking-wider">
                            <th class="px-6 py-4">รหัสเข้าใช้งาน (Code)</th>
                            <th class="px-6 py-4">สิทธิ์ (Role)</th>
                            <th class="px-6 py-4">อุปกรณ์ที่ผูกไว้ (Locked Device)</th>
                            <th class="px-6 py-4">วันหมดอายุ (Expires At)</th>
                            <th class="px-6 py-4">วันที่สร้าง (Created At)</th>
                            <th class="px-6 py-4">สถานะ (Status)</th>
                            <th class="px-6 py-4 text-center">จัดการ (Actions)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-slate-300 text-sm">
                        <?php if (empty($keys)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500 font-medium">ยังไม่มีรหัสในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($keys as $k): 
                                $isExpired = $k['is_expired'] ?? false;
                                $isAdmin = $k['role'] === 'admin';
                            ?>
                                <tr class="hover:bg-slate-900/20 transition duration-150">
                                    <td class="px-6 py-4 font-mono font-bold text-white selection:bg-brand-500/30">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate"><?= View::e($k['code']) ?></span>
                                            <button type="button" onclick="copyToClipboard('<?= View::e($k['code']) ?>', this)" class="p-1 rounded bg-slate-800/80 hover:bg-indigo-600 text-slate-400 hover:text-white transition duration-200 shrink-0" title="คัดลอกรหัส">
                                                <i class="fa-solid fa-copy text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($isAdmin): ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">Admin</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-800 text-slate-400">User</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($isAdmin): ?>
                                            <span class="text-slate-600 font-medium">-</span>
                                        <?php elseif ($k['hwid'] === null): ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500/10 border border-amber-500/20 text-amber-400">ยังไม่ผูกอุปกรณ์</span>
                                        <?php else: ?>
                                            <div class="flex flex-col gap-0.5">
                                                <span class="font-bold text-white flex items-center gap-1.5">
                                                    <i class="fa-solid fa-desktop text-xs text-indigo-400"></i>
                                                    <?= View::e($parseUa($k['user_agent'])) ?>
                                                </span>
                                                <span class="text-xs text-slate-400 font-mono">IP: <?= View::e($k['ip_address'] ?? '0.0.0.0') ?></span>
                                                <?php if (!empty($k['last_active_at'])): ?>
                                                    <span class="text-xs text-slate-500">ใช้งานล่าสุด: <?= date('d/m/Y H:i', strtotime($k['last_active_at'])) ?></span>
                                                <?php endif ?>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-400">
                                        <?= $k['expires_at'] ? date('d/m/Y H:i', strtotime($k['expires_at'])) : '<span class="text-slate-600 font-medium">ไม่มีวันหมดอายุ</span>' ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500">
                                        <?= date('d/m/Y H:i', strtotime($k['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($isAdmin): ?>
                                            <span class="flex items-center gap-1.5 text-indigo-400 font-bold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                                ถาวร
                                            </span>
                                        <?php elseif ($isExpired): ?>
                                            <span class="flex items-center gap-1.5 text-red-400 font-bold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                หมดอายุ
                                            </span>
                                        <?php else: ?>
                                            <span class="flex items-center gap-1.5 text-emerald-400 font-bold animate-pulse">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                ใช้งานได้
                                            </span>
                                        <?php endif ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <?php if ($k['role'] === 'user' && $k['hwid'] !== null): ?>
                                                <form action="/admin/keys/reset-hwid" method="POST" onsubmit="return confirmResetHwid(this, '<?= View::e($k['code']) ?>');">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                                    <button type="submit" class="p-1.5 rounded-lg border border-indigo-500/20 bg-indigo-500/5 hover:bg-indigo-500/20 text-indigo-400 hover:text-indigo-300 transition duration-200" title="ปลดล็อกอุปกรณ์ (Reset HWID)">
                                                        <i class="fa-solid fa-arrows-rotate text-sm"></i>
                                                    </button>
                                                </form>
                                            <?php endif ?>
                                            <form action="/admin/keys/delete" method="POST" onsubmit="return confirmDelete(this, '<?= View::e($k['code']) ?>');">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg border border-red-500/20 bg-red-500/5 hover:bg-red-500/20 text-red-400 hover:text-red-300 transition duration-200" title="ลบรหัส">
                                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php View::start('scripts'); ?>
<script>
function toggleDuration(role) {
    const durationWrapper = document.getElementById('duration-wrapper');
    const customExpiryWrapper = document.getElementById('custom-expiry-wrapper');
    if (role === 'admin') {
        durationWrapper.style.display = 'none';
        customExpiryWrapper.style.display = 'none';
    } else {
        durationWrapper.style.display = 'block';
        toggleDurationField(document.getElementById('duration').value);
    }
}

function toggleDurationField(val) {
    const customExpiryWrapper = document.getElementById('custom-expiry-wrapper');
    if (val === 'custom') {
        customExpiryWrapper.style.display = 'block';
        // Set default minimum date-time to now in local timezone
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('custom_expiry').min = now.toISOString().slice(0, 16);
    } else {
        customExpiryWrapper.style.display = 'none';
    }
}

$(document).ready(function() {
    // Initialize DataTables with fully responsive Thai localized properties
    $('#keysTable').DataTable({
        "order": [[4, "desc"]], // Sort by Created At column descending (updated index due to new column)
        "language": {
            "search": "ค้นหารหัส:",
            "lengthMenu": "แสดง _MENU_ รายการ",
            "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
            "infoEmpty": "แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ",
            "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
            "zeroRecords": "ไม่พบข้อมูลที่ค้นหา",
            "paginate": {
                "first": "หน้าแรก",
                "last": "หน้าสุดท้าย",
                "next": "ถัดไป",
                "previous": "ก่อนหน้า"
            }
        }
    });

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
});

// SweetAlert2 Confirmation Dialog for resetting HWID
function confirmResetHwid(form, code) {
    Swal.fire({
        title: 'ยืนยันปลดล็อกอุปกรณ์?',
        text: "รหัสเข้าใช้งาน " + code + " จะถูกปลดการผูกเครื่องเดิมออก ทำให้สามารถนำไปใช้ล็อกอินเข้าใช้งานบนบราวเซอร์หรืออุปกรณ์เครื่องใหม่ได้ทันที!",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#1e293b',
        confirmButtonText: 'ใช่, ปลดล็อกอุปกรณ์!',
        cancelButtonText: 'ยกเลิก',
        background: '#090d16',
        color: '#cbd5e1'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}

// SweetAlert2 Confirmation Dialog for key deletion
function confirmDelete(form, code) {
    Swal.fire({
        title: 'ยืนยันการเพิกถอนสิทธิ์?',
        text: "รหัสเข้าใช้งาน " + code + " จะถูกเพิกถอนและไม่สามารถเข้าใช้งานระบบได้อีกทันที!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#1e293b',
        confirmButtonText: 'ใช่, เพิกถอนสิทธิ์!',
        cancelButtonText: 'ยกเลิก',
        background: '#090d16',
        color: '#cbd5e1'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}

// Copy UUID v7 keycode to clipboard with visual check and SweetAlert2 Toast Feedback
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        const oldClass = icon.className;
        
        // Temporarily swap copy icon to checkmark icon & transition to emerald glow
        icon.className = 'fa-solid fa-check text-xs';
        btn.classList.remove('bg-slate-800/80', 'hover:bg-indigo-600', 'text-slate-400');
        btn.classList.add('bg-emerald-600', 'text-white');
        
        // Trigger a gorgeous SweetAlert2 top-end toast
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'คัดลอกรหัสสำเร็จ!',
            text: 'คัดลอกรหัสไปยังคลิปบอร์ดแล้ว',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            background: '#090d16',
            color: '#cbd5e1'
        });
        
        // Restore to original state after 1.5 seconds
        setTimeout(() => {
            icon.className = oldClass;
            btn.classList.remove('bg-emerald-600', 'text-white');
            btn.classList.add('bg-slate-800/80', 'hover:bg-indigo-600', 'text-slate-400');
        }, 1500);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}
</script>
<?php View::stop(); ?>
