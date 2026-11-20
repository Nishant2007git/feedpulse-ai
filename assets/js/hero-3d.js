/**
 * FeedPulse AI — Interactive 3D Holographic AI Sentiment Engine
 * Powered by Three.js WebGL & Spline 3D Integration
 */

class FeedPulse3DEngine {
  constructor() {
    this.container = document.getElementById('hero-3d-container');
    if (!this.container) return;

    this.mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };
    this.init();
  }

  init() {
    this.initInteractiveWebGL();
    this.setupEventListeners();
  }

  initInteractiveWebGL() {
    const canvas = document.getElementById('webgl-orb-canvas');
    if (!canvas || typeof THREE === 'undefined') return;

    const width = this.container.clientWidth || 480;
    const height = 440;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.z = 4.8;

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    // 1. Core Glowing Sphere (Holographic AI Sentiment Mesh)
    const coreGeometry = new THREE.IcosahedronGeometry(1.2, 4);
    const coreMaterial = new THREE.MeshPhongMaterial({
      color: 0x3B82F6,
      emissive: 0x1E3A8A,
      specular: 0x22D3EE,
      shininess: 95,
      wireframe: true,
      transparent: true,
      opacity: 0.72
    });
    const coreMesh = new THREE.Mesh(coreGeometry, coreMaterial);
    scene.add(coreMesh);

    // 2. Inner Pulsing Core
    const innerGeom = new THREE.SphereGeometry(0.85, 32, 32);
    const innerMat = new THREE.MeshBasicMaterial({
      color: 0x8B5CF6,
      transparent: true,
      opacity: 0.28,
      blending: THREE.AdditiveBlending
    });
    const innerMesh = new THREE.Mesh(innerGeom, innerMat);
    scene.add(innerMesh);

    // 3. Floating Stardust Particles
    const particlesCount = 420;
    const posArray = new Float32Array(particlesCount * 3);
    for (let i = 0; i < particlesCount * 3; i += 3) {
      const r = 1.0 + Math.random() * 0.55;
      const theta = Math.random() * Math.PI * 2;
      const phi = Math.acos((Math.random() * 2) - 1);
      posArray[i] = r * Math.sin(phi) * Math.cos(theta);
      posArray[i + 1] = r * Math.sin(phi) * Math.sin(theta);
      posArray[i + 2] = r * Math.cos(phi);
    }
    const particleGeometry = new THREE.BufferGeometry();
    particleGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
    const particleMaterial = new THREE.PointsMaterial({
      size: 0.038,
      color: 0x22D3EE,
      transparent: true,
      opacity: 0.85,
      blending: THREE.AdditiveBlending
    });
    const particleMesh = new THREE.Points(particleGeometry, particleMaterial);
    scene.add(particleMesh);

    // 4. Orbiting Rings (Safely contained inside canvas bounds)
    const ring1Geom = new THREE.TorusGeometry(1.6, 0.015, 16, 100);
    const ring1Mat = new THREE.MeshBasicMaterial({ color: 0x06B6D4, transparent: true, opacity: 0.65 });
    const ring1 = new THREE.Mesh(ring1Geom, ring1Mat);
    ring1.rotation.x = Math.PI / 3;
    scene.add(ring1);

    const ring2Geom = new THREE.TorusGeometry(1.85, 0.012, 16, 100);
    const ring2Mat = new THREE.MeshBasicMaterial({ color: 0xA78BFA, transparent: true, opacity: 0.55 });
    const ring2 = new THREE.Mesh(ring2Geom, ring2Mat);
    ring2.rotation.y = Math.PI / 4;
    ring2.rotation.x = -Math.PI / 5;
    scene.add(ring2);

    // 5. Orbiting Data Satellite (Contained orbit)
    const satGeom = new THREE.SphereGeometry(0.09, 16, 16);
    const satMat = new THREE.MeshStandardMaterial({ color: 0x10B981, emissive: 0x059669 });
    const satellite = new THREE.Mesh(satGeom, satMat);
    scene.add(satellite);

    // Lighting Setup
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
    scene.add(ambientLight);

    const cyanLight = new THREE.PointLight(0x06B6D4, 2.2, 30);
    cyanLight.position.set(3, 2, 4);
    scene.add(cyanLight);

    const violetLight = new THREE.PointLight(0x8B5CF6, 2.2, 30);
    violetLight.position.set(-3, -2, -3);
    scene.add(violetLight);

    // Animation Loop
    let clock = new THREE.Clock();

    const animate = () => {
      requestAnimationFrame(animate);
      const elapsedTime = clock.getElapsedTime();

      // Smooth mouse damping
      this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.06;
      this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.06;

      coreMesh.rotation.y = elapsedTime * 0.22 + this.mouse.x * 0.75;
      coreMesh.rotation.x = elapsedTime * 0.14 + this.mouse.y * 0.75;

      const pulse = 1 + Math.sin(elapsedTime * 2) * 0.04;
      innerMesh.scale.set(pulse, pulse, pulse);

      particleMesh.rotation.y = -elapsedTime * 0.16;
      particleMesh.rotation.x = elapsedTime * 0.10;

      ring1.rotation.z = elapsedTime * 0.32;
      ring1.rotation.y = elapsedTime * 0.18;

      ring2.rotation.z = -elapsedTime * 0.24;
      ring2.rotation.x = elapsedTime * 0.26;

      // Orbit satellite within visible frustum
      const satAngle = elapsedTime * 1.1;
      satellite.position.x = Math.cos(satAngle) * 1.6;
      satellite.position.z = Math.sin(satAngle) * 1.6;
      satellite.position.y = Math.sin(elapsedTime * 1.8) * 0.45;

      renderer.render(scene, camera);
    };

    animate();

    // Resize listener
    window.addEventListener('resize', () => {
      const newW = this.container.clientWidth || 480;
      camera.aspect = newW / height;
      camera.updateProjectionMatrix();
      renderer.setSize(newW, height);
    });
  }

  setupEventListeners() {
    window.addEventListener('mousemove', (e) => {
      const normX = (e.clientX / window.innerWidth) * 2 - 1;
      const normY = -(e.clientY / window.innerHeight) * 2 + 1;
      this.mouse.targetX = normX;
      this.mouse.targetY = normY;
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new FeedPulse3DEngine();
});
