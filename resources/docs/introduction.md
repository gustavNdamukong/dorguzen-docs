<div class="dgz-hero">
  <img src="/dorguzen-docs/assets/images/dorguzen-logo.png" alt="Dorguzen" class="dgz-hero-logo" id="dgz-intro-logo">
  <h1 class="dgz-hero-name">Dorgu<span>zen</span></h1>
  <p class="dgz-hero-tagline">Build it clean. Ship it fast. Own every line.</p>
  <p class="dgz-hero-desc">
    Dorguzen is a modern PHP MVC framework that gives you everything you need to build a
    full-featured web application — without hiding how any of it works.
    Clear code, honest architecture, and documentation that explains the <em>why</em>, not just the <em>what</em>.
  </p>
  <div class="dgz-hero-cta">
    <a href="/dorguzen-docs/docs/installation" class="dgz-btn dgz-btn-primary">
      <i class="fas fa-rocket"></i> Get Started
    </a>
    <a href="https://github.com/gustavNdamukong/Dorguzen" target="_blank" class="dgz-btn dgz-btn-outline">
      <i class="fab fa-github"></i> View on GitHub
    </a>
  </div>
</div>

<hr class="dgz-divider">

## Why Dorguzen?

Most frameworks wrap PHP in so many layers of abstraction that you end up learning the framework's own private language instead of actual PHP. When something breaks, you're lost. When you move on to another project, you have to start over.

**Dorguzen is different.** It keeps all the convenience, but strips away the black box. Every feature is built on straightforward PHP patterns. The source code is readable. The documentation explains what is happening and why. When you understand Dorguzen, you understand PHP — and that knowledge travels with you.

> **Dorguzen is not just a tool for shipping faster. It is a tool for becoming a better PHP developer.**

<div class="dgz-features">
  <div class="dgz-feature-card">
    <i class="fas fa-route dgz-feature-icon"></i>
    <div class="dgz-feature-title">Routing</div>
    <p class="dgz-feature-desc">Clean URLs, named routes, route parameters, middleware groups, and auto-discovery — all in one place.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-database dgz-feature-icon"></i>
    <div class="dgz-feature-title">Models & ORM</div>
    <p class="dgz-feature-desc">A lightweight ORM that reads like plain PHP. Relationships, cascading deletes, and multiple DB drivers (MySQL, SQLite, PostgreSQL).</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-shield-halved dgz-feature-icon"></i>
    <div class="dgz-feature-title">Authentication</div>
    <p class="dgz-feature-desc">Login, registration, password reset, and role-based access control built in. Four user roles out of the box.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-plug dgz-feature-icon"></i>
    <div class="dgz-feature-title">REST API</div>
    <p class="dgz-feature-desc">A dedicated versioned API layer with JWT authentication and auto-generated Swagger UI documentation.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-layer-group dgz-feature-icon"></i>
    <div class="dgz-feature-title">Events & Queues</div>
    <p class="dgz-feature-desc">Decouple your application with events and listeners. Push slow work (emails, exports) to background job queues.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-terminal dgz-feature-icon"></i>
    <div class="dgz-feature-title">CLI Tool</div>
    <p class="dgz-feature-desc">Generate controllers, models, migrations, jobs, and more with <code>php dgz make:*</code>. Run your scheduled tasks and queue workers too.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-envelope dgz-feature-icon"></i>
    <div class="dgz-feature-title">Email & Newsletter</div>
    <p class="dgz-feature-desc">PHPMailer integration with queue-based bulk newsletter support, subscriber management, and customisable templates.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-magnifying-glass dgz-feature-icon"></i>
    <div class="dgz-feature-title">SEO Module</div>
    <p class="dgz-feature-desc">Database-driven meta tags, Open Graph, Twitter Card, and canonical URLs — automatically applied per page from the admin panel.</p>
  </div>
  <div class="dgz-feature-card">
    <i class="fas fa-chart-bar dgz-feature-icon"></i>
    <div class="dgz-feature-title">Admin Dashboard</div>
    <p class="dgz-feature-desc">A fully working admin interface ships with the framework — ready the moment you install it.</p>
  </div>
</div>

<hr class="dgz-divider">

## A Full Application, Ready on Day One

Most frameworks give you a blank slate. Dorguzen gives you a **fully functional website** the moment you install it. Your application ships with a complete admin dashboard and a set of ready-made content modules that are already wired up, styled, and working.

<div class="dgz-modules">
  <div class="dgz-modules-title"><i class="fas fa-cubes" style="margin-right:.5rem"></i>Built-in Content Modules</div>
  <p class="dgz-modules-subtitle">
    Every module is fully functional on install — public-facing pages, admin management, image uploads, and all.
    Don't need one? Turn it off in <code style="background:rgba(255,255,255,.1);color:inherit;border:none">.env</code> with a single setting.
  </p>
  <div class="dgz-modules-grid">
    <div class="dgz-module-pill">
      <i class="fas fa-pen-nib"></i> Blog
    </div>
    <div class="dgz-module-pill">
      <i class="fas fa-newspaper"></i> News
    </div>
    <div class="dgz-module-pill">
      <i class="fas fa-images"></i> Gallery
    </div>
    <div class="dgz-module-pill">
      <i class="fas fa-briefcase"></i> Portfolio
    </div>
    <div class="dgz-module-pill">
      <i class="fas fa-video"></i> Videos
    </div>
    <div class="dgz-module-pill">
      <i class="fas fa-envelope-open-text"></i> Newsletter
    </div>
  </div>
</div>

Here is what that means in practice. A startup building a company website can install Dorguzen and immediately have:

- A **blog** for publishing articles (with categories, tags, drafts, and a full publishing workflow)
- A **news** section for announcements
- A **portfolio** to showcase work
- A **gallery** for photo albums
- A **videos** page for embedded YouTube or Vimeo clips
- A **newsletter** system where visitors can subscribe, and admins can send broadcast emails

If your application does not need one of those modules, just set `MODULES_BLOG_STATUS=off` (or whichever module) in your `.env` file and remove the link from your navigation menu. No code changes needed.

<hr class="dgz-divider">

<div class="dgz-philosophy">
  <div class="dgz-philosophy-quote">"A Dorguzen developer genuinely understands the architecture behind what they are building."</div>
  <p class="dgz-philosophy-sub">
    Dorguzen is designed to be transparent. Every feature is readable, followable PHP — no compiled internals, no vendor magic.
    When something does not behave as you expect, you can open the relevant class and read exactly what is happening.
    This makes Dorguzen an excellent choice both for production applications and for developers who want to deepen their
    understanding of how PHP frameworks actually work under the hood.
  </p>
</div>

<hr class="dgz-divider">

## What is in These Docs?

This documentation covers everything in the framework. Use the sidebar to navigate. Here is a quick map:

| Section | What you will find |
|---|---|
| **Getting Started** | Installation, configuration, and a tour of the project structure |
| **Core Concepts** | Routing, controllers, forms, validation, views, models, and dependency injection |
| **Going Deeper** | Authentication, middleware, error handling, migrations, queues, events, and the scheduler |
| **Features** | File uploads, email, REST API, CLI tool, SEO, security, and testing |
| **Built-in Modules** | Deep dives on Blog, News, Gallery, Portfolio, and Videos |
| **Deployment** | Deploying to shared hosting or a VPS, performance caching, and production checklist |

Ready? Let's go.

<div class="dgz-hero-cta" style="justify-content:flex-start;margin-top:2rem;margin-bottom:0">
  <a href="/dorguzen-docs/docs/installation" class="dgz-btn dgz-btn-primary">
    <i class="fas fa-arrow-right"></i> Start with Installation
  </a>
</div>
