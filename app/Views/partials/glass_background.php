<?php
/**
 * Animated liquid-glass background: blurred morphing blobs + floating 3D glass objects.
 */
?>
<div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-[radial-gradient(ellipse_at_top,_#1e1b4b_0%,_#0b1120_55%,_#020617_100%)]">
    <!-- Morphing color blobs -->
    <div class="blob left-[-10%] top-[-10%] h-[45vw] w-[45vw] bg-cyan-500"></div>
    <div class="blob right-[-15%] top-[10%] h-[40vw] w-[40vw] bg-fuchsia-600 [animation-delay:-6s]"></div>
    <div class="blob bottom-[-20%] left-[20%] h-[50vw] w-[50vw] bg-indigo-600 [animation-delay:-12s]"></div>
    <div class="blob bottom-[5%] right-[10%] h-[25vw] w-[25vw] bg-emerald-400 opacity-40 [animation-delay:-3s]"></div>

    <!-- 3D glass cube -->
    <div class="scene left-[8%] top-[18%] h-28 w-28 animate-float blur-[1px]">
        <div class="cube h-28 w-28">
            <span style="transform: translateZ(56px)"></span>
            <span style="transform: rotateY(180deg) translateZ(56px)"></span>
            <span style="transform: rotateY(90deg) translateZ(56px)"></span>
            <span style="transform: rotateY(-90deg) translateZ(56px)"></span>
            <span style="transform: rotateX(90deg) translateZ(56px)"></span>
            <span style="transform: rotateX(-90deg) translateZ(56px)"></span>
        </div>
    </div>

    <!-- 3D small cube -->
    <div class="scene bottom-[14%] right-[12%] h-16 w-16 animate-float blur-[2px] [animation-delay:-4s]">
        <div class="cube h-16 w-16 [animation-direction:reverse] [animation-duration:18s]">
            <span style="transform: translateZ(32px)"></span>
            <span style="transform: rotateY(180deg) translateZ(32px)"></span>
            <span style="transform: rotateY(90deg) translateZ(32px)"></span>
            <span style="transform: rotateY(-90deg) translateZ(32px)"></span>
            <span style="transform: rotateX(90deg) translateZ(32px)"></span>
            <span style="transform: rotateX(-90deg) translateZ(32px)"></span>
        </div>
    </div>

    <!-- 3D torus rings -->
    <div class="scene right-[18%] top-[8%] h-44 w-44 blur-[2px]">
        <div class="ring3d"></div>
        <div class="ring3d [animation-delay:-8s] [animation-duration:30s] border-fuchsia-300/20"></div>
    </div>
    <div class="scene bottom-[8%] left-[14%] h-32 w-32 animate-float blur-[3px] [animation-delay:-2s]">
        <div class="ring3d border-cyan-200/25 [animation-duration:20s]"></div>
    </div>

    <!-- Glass sphere -->
    <div class="absolute left-[55%] top-[65%] h-24 w-24 animate-float rounded-full border border-white/30 bg-gradient-to-br from-white/40 via-white/5 to-transparent shadow-[inset_-10px_-10px_30px_rgba(255,255,255,0.15),0_20px_40px_rgba(0,0,0,0.4)] backdrop-blur-md [animation-delay:-6s]"></div>

    <!-- Grain / vignette -->
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_40%,rgba(2,6,23,0.75)_100%)]"></div>
</div>
