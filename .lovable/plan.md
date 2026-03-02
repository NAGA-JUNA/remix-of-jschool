

# Add Fade-In Animation to Leadership Slider Section

## Change
Add a smooth fade-in + slide-up animation to the Leadership section when it scrolls into view, using the existing `IntersectionObserver` pattern already used for the quote banner.

## Implementation (1 file: `php-backend/public/about.php`)

### 1. Set initial hidden state on the Leadership section
Add `opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease, transform 0.8s ease;` as an inline style on the Leadership `<section>` element (line 331), and give it an ID like `leadershipSection` for targeting.

### 2. Observe the section with IntersectionObserver
In the existing script block (around line 468), extend the observer to also observe the leadership section:

```javascript
document.querySelectorAll('.quote-banner, #leadershipSection').forEach(el => observer.observe(el));
```

This reuses the same observer that already fades in the quote banner -- when the leadership section scrolls 20% into view, it transitions to `opacity: 1` and `translateY(0)`.

### Result
The entire Leadership slider section (title, subtitle, slider, and controls) smoothly fades in and slides up when the user scrolls down to it. No new JS logic needed -- just reuses the existing observer.

