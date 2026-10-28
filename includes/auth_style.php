<?php
/**
 * Shared look for the public auth pages (login / register / reset / verify).
 *
 * Background + colour palette are taken from the DGT One reference login screen:
 * a pale cool-grey canvas with a white centre wash and slow floating squares
 * (AUTH_SQUARES), a white card, an indigo primary button, navy links, and
 * red validation states. Defines the class-string constants the pages reuse and
 * emits the one <style> block needed for the background.
 *
 * require this in <head> AFTER includes/tailwind.php.
 */
if (!defined('AUTH_INPUT')) {
    define('AUTH_INPUT',
        'w-full rounded-lg border border-[#d6dbe6] bg-white px-3.5 py-3 text-sm text-[#1b2a4a] '
        . 'outline-none transition placeholder:text-[#9aa3b4] '
        . 'focus:border-[#4361ee] focus:ring-4 focus:ring-[#4361ee]/15 '
        . 'dark:border-white/15 dark:bg-navy-900 dark:text-steel-50 dark:placeholder:text-steel-500');
    define('AUTH_BTN',
        'w-full rounded-lg bg-[#4361ee] px-4 py-3 text-center text-sm font-semibold text-white '
        . 'transition hover:bg-[#3a52d6] disabled:opacity-50');
    define('AUTH_BTN_GHOST',
        'w-full rounded-lg border border-[#4361ee] bg-white px-4 py-3 text-center text-sm font-semibold '
        . 'text-[#4361ee] transition hover:bg-[#4361ee]/[0.06] dark:bg-transparent dark:hover:bg-[#4361ee]/[0.15]');
    define('AUTH_LINK', 'font-semibold text-[#1b2a4a] hover:underline dark:text-steel-100');
    define('AUTH_CARD',
        'w-full max-w-[430px] rounded-2xl border border-[#e7e9f1] bg-white px-8 pb-8 pt-9 '
        . 'shadow-[0_16px_50px_rgba(28,42,74,0.10)] dark:border-white/10 dark:bg-navy-800');
    define('AUTH_SQUARES', '<ul class="auth-squares" aria-hidden="true">' . str_repeat('<li></li>', 10) . '</ul>');
    define('AUTH_BODY', 'auth-bg flex min-h-screen items-center justify-center px-5 py-9 font-sans text-[#1b2a4a] antialiased dark:text-steel-50');
}
?>
<style>
    /* Pale canvas + a soft white centre wash. The old polygon watermark was
       replaced by the floating squares below (AUTH_SQUARES, placed right
       after <body> on each page). */
    .auth-bg {
        background-color: #eef1f7;
        background-image: radial-gradient(ellipse 68% 60% at 50% 32%, #ffffff 0%, rgba(255, 255, 255, 0) 72%);
        background-size: 100% 100%;
        background-repeat: no-repeat;
    }
    html.dark .auth-bg {
        background-color: #161d24;
        background-image: radial-gradient(ellipse 68% 60% at 50% 32%, #1e3350 0%, rgba(30, 51, 80, 0) 72%);
    }
    /* Floating squares behind the card: slow, autonomous rise + spin (no
       pointer tracking). DGT blue / teal outlines and soft fills. */
    .auth-squares { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
    .auth-squares li {
        position: absolute; bottom: -160px; display: block; list-style: none;
        border-radius: 14px; border: 2px solid rgba(0,108,192,.22); background: rgba(0,108,192,.05);
        animation: sq-rise 24s linear infinite;
    }
    .auth-squares li:nth-child(even) { border-color: rgba(0,134,160,.24); background: rgba(0,134,160,.06); }
    .auth-squares li:nth-child(3n) { background: transparent; }
    html.dark .auth-squares li { border-color: rgba(122,178,232,.22); background: rgba(122,178,232,.05); }
    html.dark .auth-squares li:nth-child(even) { border-color: rgba(80,190,210,.22); background: rgba(80,190,210,.05); }
    .auth-squares li:nth-child(1)  { left: 6%;  width: 70px;  height: 70px;  animation-duration: 26s; }
    .auth-squares li:nth-child(2)  { left: 16%; width: 34px;  height: 34px;  animation-duration: 18s; animation-delay: -4s; }
    .auth-squares li:nth-child(3)  { left: 27%; width: 110px; height: 110px; animation-duration: 32s; animation-delay: -12s; }
    .auth-squares li:nth-child(4)  { left: 38%; width: 46px;  height: 46px;  animation-duration: 22s; animation-delay: -18s; }
    .auth-squares li:nth-child(5)  { left: 52%; width: 28px;  height: 28px;  animation-duration: 16s; animation-delay: -7s; }
    .auth-squares li:nth-child(6)  { left: 63%; width: 90px;  height: 90px;  animation-duration: 28s; animation-delay: -2s; }
    .auth-squares li:nth-child(7)  { left: 74%; width: 40px;  height: 40px;  animation-duration: 20s; animation-delay: -14s; }
    .auth-squares li:nth-child(8)  { left: 84%; width: 130px; height: 130px; animation-duration: 36s; animation-delay: -20s; }
    .auth-squares li:nth-child(9)  { left: 92%; width: 30px;  height: 30px;  animation-duration: 17s; animation-delay: -9s; }
    .auth-squares li:nth-child(10) { left: 45%; width: 60px;  height: 60px;  animation-duration: 30s; animation-delay: -25s; }
    @keyframes sq-rise {
        0%   { transform: translateY(0) rotate(0deg); opacity: 0; }
        10%  { opacity: 1; }
        90%  { opacity: 1; }
        100% { transform: translateY(calc(-100vh - 320px)) rotate(540deg); opacity: 0; }
    }
    .auth-squares ~ * { position: relative; z-index: 1; }
    @media (prefers-reduced-motion: reduce) {
        .auth-squares li { animation: none; bottom: auto; }
        .auth-squares li:nth-child(odd) { top: 18%; }
        .auth-squares li:nth-child(even) { top: 68%; }
    }
</style>
