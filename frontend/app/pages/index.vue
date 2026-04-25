<template>
  <div class="landing">
    <div class="glow glow-1" />
    <div class="glow glow-2" />

    <nav class="nav">
      <span class="logo">Loci OS</span>
    </nav>

    <main class="hero">
      <h1 class="title">Your memory palace</h1>
      <p class="sub">Store, search, and converse with everything you know.</p>
      <div class="actions">
        <NuxtLink to="/auth?mode=login" class="btn btn-secondary">Log in</NuxtLink>
        <NuxtLink to="/auth?mode=register" class="btn btn-primary">Get started</NuxtLink>
      </div>
    </main>
  </div>
</template>

<style scoped>
.landing {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  background: var(--bg);
}

.glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  pointer-events: none;
}
.glow-1 {
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(124,58,237,0.25) 0%, transparent 70%);
  top: -100px; left: -100px;
}
.glow-2 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
  bottom: -50px; right: -50px;
}

.nav {
  padding: 1.5rem 2rem;
  position: relative;
  z-index: 1;
}
.logo {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  background: linear-gradient(135deg, #a78bfa, #6366f1);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem;
  position: relative;
  z-index: 1;
}

.title {
  font-size: clamp(2.5rem, 6vw, 5rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.1;
  background: linear-gradient(160deg, #fff 0%, #a78bfa 60%, #6366f1 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 1.25rem;
}

.sub {
  color: var(--muted);
  font-size: 1.125rem;
  max-width: 480px;
  line-height: 1.6;
  margin-bottom: 2.5rem;
}

.actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }

.btn {
  display: inline-flex;
  align-items: center;
  padding: 0.75rem 2rem;
  border-radius: var(--radius);
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
  border: none;
}
.btn-primary {
  background: var(--accent);
  color: #fff;
}
.btn-primary:hover { background: #6d28d9; transform: translateY(-1px); }
.btn-secondary {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text);
}
.btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
</style>
