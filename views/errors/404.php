<?php
use App\Core\View;
View::layout('layouts/main');
View::title('404 — ไม่พบหน้าที่ต้องการ');
View::start('content');
?>
<div class="relative min-h-[75vh] flex items-center justify-center px-4 overflow-hidden">
    <!-- Extra Glowing Blobs specifically for 404 Page (Wow effect!) -->
    <div class="absolute w-80 h-80 rounded-full bg-brand-500/10 blur-[100px] top-1/4 left-1/3 animate-pulse pointer-events-none" style="animation-duration: 6s;"></div>
    <div class="absolute w-96 h-96 rounded-full bg-indigo-500/10 blur-[120px] bottom-1/4 right-1/3 animate-pulse pointer-events-none" style="animation-duration: 8s;"></div>

    <div class="relative z-10 max-w-lg w-full text-center">
        <!-- Floating Cinematic Icon with Outer Glow -->
        <div class="relative w-28 h-28 mx-auto mb-8 flex items-center justify-center bg-slate-900/40 border border-slate-800/80 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.8)] backdrop-blur-md group animate-bounce-slow">
            <!-- Glow background effect on hover -->
            <div class="absolute inset-0 rounded-3xl bg-gradient-to-tr from-brand-500/20 to-indigo-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <i class="fa-solid fa-video-slash text-4xl bg-gradient-to-r from-brand-400 to-indigo-400 bg-clip-text text-transparent drop-shadow-[0_0_15px_rgba(99,102,241,0.5)]"></i>
        </div>

        <!-- Huge Glowing 404 text -->
        <h1 class="text-8xl sm:text-9xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-r from-brand-400 via-violet-400 to-indigo-400 drop-shadow-[0_10px_30px_rgba(99,102,241,0.25)] select-none animate-pulse-slow">
            404
        </h1>

        <!-- Subtitles -->
        <h2 class="mt-4 font-black text-xl sm:text-2xl text-white font-heading tracking-tight">ไม่พบหน้าที่คุณต้องการรับชม</h2>
        
        <p class="mt-3 text-slate-450 text-sm max-w-sm mx-auto leading-relaxed">
            ภาพยนตร์หรือเส้นทาง URL ที่คุณพยายามเข้าถึงอาจถูกลบออกแล้ว เปลี่ยนชื่อลิงก์ใหม่ หรือไม่มีอยู่จริงในระบบ SeriesJeen
        </p>

        <!-- Dynamic Action Buttons -->
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="/" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl font-bold bg-brand-500 hover:bg-brand-600 text-white shadow-lg shadow-brand-500/20 hover:shadow-brand-500/35 hover:-translate-y-0.5 transition-all duration-300 active:scale-95 text-sm">
                <i class="fa-solid fa-house text-xs"></i> กลับสู่หน้าหลัก
            </a>
            
            <button onclick="history.back();" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl font-semibold bg-slate-900/60 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 hover:border-slate-700 transition-all duration-300 active:scale-95 text-sm">
                <i class="fa-solid fa-arrow-left text-xs"></i> ย้อนกลับไปเมื่อครู่
            </button>
        </div>
    </div>
</div>

<style>
@keyframes bounce-slow {
    0%, 100% {
        transform: translateY(0);
        animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
    }
    50% {
        transform: translateY(-8px);
        animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
    }
}
.animate-bounce-slow {
    animation: bounce-slow 4s infinite;
}
@keyframes pulse-slow {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.88;
        transform: scale(0.98);
    }
}
.animate-pulse-slow {
    animation: pulse-slow 3s ease-in-out infinite;
}
</style>
<?php View::stop();
?>
