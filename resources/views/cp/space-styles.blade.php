/* Space-decor van het agent-dashboard — zelfde recept als de verkoopsite
   ("hoe het werkt"-journey + marketing-footer): #070b29-gradient, geanimeerde
   nevels, planeet met ring, twinkelende sterren en vallende sterren. */

.sncp-space {
  background:
    radial-gradient(
      900px 520px at 12% -8%,
      rgba(124, 77, 255, 0.26),
      transparent 60%
    ),
    radial-gradient(
      820px 460px at 92% 8%,
      rgba(33, 207, 230, 0.18),
      transparent 62%
    ),
    linear-gradient(180deg, #070b29 0%, #0a0f30 60%, #080c2a 100%);
}

@keyframes sncp-space-twinkle {
  0%,
  100% {
    opacity: 0.2;
  }
  50% {
    opacity: 1;
  }
}

@keyframes sncp-space-nebula {
  0%,
  100% {
    opacity: 0.45;
    transform: scale(1);
  }
  50% {
    opacity: 0.8;
    transform: scale(1.08);
  }
}

@keyframes sncp-space-floaty {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-16px);
  }
}

@keyframes sncp-space-shoot {
  0% {
    transform: translate(0, 0) rotate(20deg);
    opacity: 0;
  }
  8% {
    opacity: 1;
  }
  45% {
    opacity: 1;
  }
  70% {
    transform: translate(420px, 150px) rotate(20deg);
    opacity: 0;
  }
  100% {
    opacity: 0;
  }
}

.sncp-space__nebula {
  position: absolute;
  border-radius: 50%;
  filter: blur(20px);
  animation: sncp-space-nebula 9s ease-in-out infinite;
}

.sncp-space__nebula--1 {
  top: -80px;
  left: -60px;
  width: 560px;
  height: 460px;
  background: radial-gradient(circle, rgba(124, 77, 255, 0.28), transparent 68%);
}

.sncp-space__nebula--2 {
  top: 40%;
  right: -80px;
  width: 520px;
  height: 520px;
  filter: blur(24px);
  background: radial-gradient(circle, rgba(33, 207, 230, 0.2), transparent 66%);
  animation-duration: 11s;
  animation-delay: 1.5s;
}

.sncp-space__nebula--3 {
  bottom: -120px;
  left: 30%;
  width: 620px;
  height: 420px;
  filter: blur(26px);
  background: radial-gradient(circle, rgba(79, 140, 240, 0.16), transparent 70%);
  animation-duration: 13s;
  animation-delay: 0.8s;
}

.sncp-space__planet {
  position: absolute;
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: radial-gradient(
    circle at 32% 30%,
    #3a4486,
    #1a2050 60%,
    #0c1138
  );
  box-shadow:
    inset -10px -12px 26px rgba(0, 0, 0, 0.55),
    0 0 50px rgba(79, 140, 240, 0.22);
  animation: sncp-space-floaty 14s ease-in-out infinite;
}

.sncp-space__planet--tl {
  top: -28px;
  left: 36px;
}

.sncp-space__planet--tr {
  top: 32px;
  right: 48px;
}

.sncp-space__planet--br {
  bottom: -20px;
  right: 40px;
}

.sncp-space__planet-ring {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 160px;
  height: 40px;
  transform: translate(-50%, -50%) rotate(-22deg);
  border-radius: 50%;
  border: 2px solid rgba(124, 77, 255, 0.32);
  border-bottom-color: rgba(33, 207, 230, 0.28);
}

.sncp-space__star {
  position: absolute;
  border-radius: 50%;
  background: #ffffff;
  opacity: 0.5;
  box-shadow: 0 0 4px rgba(255, 255, 255, 0.5);
  animation: sncp-space-twinkle 3s ease-in-out infinite;
}

.sncp-space__star.is-bright {
  box-shadow: 0 0 6px 1px rgba(191, 238, 255, 0.9);
}

.sncp-space__shoot {
  position: absolute;
  height: 2px;
  border-radius: 2px;
  animation: sncp-space-shoot 7s ease-in-out infinite;
}

.sncp-space__shoot--1 {
  top: 60px;
  left: 8%;
  width: 90px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0), #ffffff);
  box-shadow: 0 0 8px #bfeeff;
  animation-delay: 2s;
}

.sncp-space__shoot--2 {
  top: 55%;
  left: 40%;
  width: 70px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0), #bfeeff);
  box-shadow: 0 0 8px #7c4dff;
  animation-duration: 9s;
  animation-delay: 5s;
}


.sncp-space{position:relative;isolation:isolate;border:1px solid #ffffff1a;box-shadow:0 10px 30px #1e1b4b26}.sncp-space-decor{position:absolute;inset:0;overflow:hidden;pointer-events:none}.sncp-hero-copy,.sncp-visual{position:relative;z-index:1}
@media(prefers-reduced-motion:reduce){.sncp-space *{animation:none!important}}
