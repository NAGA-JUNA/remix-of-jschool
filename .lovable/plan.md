

## Update public-footer.php with WhatsApp + Need Help Features

Merge the WhatsApp floating button and "Need Help?" sticky inquiry sidebar from the current project into the uploaded `public-footer.php` file (for your other cPanel project).

---

### What Will Be Added

The uploaded file (255 lines) currently has only the footer CTA and main footer sections. I will add:

1. **WhatsApp Floating Button** (bottom-right corner)
   - Green pill-shaped button with "Chat with us" text
   - Collapses to a 52x52 circle icon on mobile
   - Reads WhatsApp number from `getSetting('whatsapp_api_number')` or a fallback variable
   - Pulse animation for visibility

2. **"Need Help?" Sticky Sidebar Tab** (right edge)
   - Red vertical tab fixed at 50% height
   - Opens a centered Bootstrap modal with a callback request form
   - Form fields: Parent Name, Mobile (+91 prefix), Email (optional), Message
   - Client-side validation (10-digit mobile, email format)
   - AJAX submission to `/public/ajax/enquiry-submit.php`
   - Success animation with auto-close after 2.5 seconds
   - Honeypot field for spam protection

3. **Associated CSS** for both floating elements (WhatsApp button styles, Need Help tab styles, responsive breakpoints)

4. **JavaScript** for form submission handling with loading spinner and error handling

### Technical Approach

- Replace the project file `php-backend/includes/public-footer.php` with the uploaded file's content as the base
- Append the WhatsApp button HTML, Need Help tab + modal HTML, CSS, and JS after the closing `</footer>` tag
- Adapt the WhatsApp number to use `getSetting()` with a fallback since the uploaded file uses different variable conventions
- Keep all existing footer content from the uploaded file exactly as-is

