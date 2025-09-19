// script.js
// Obsługa formularza — wysyłamy fetch do auth.php i obsługujemy odpowiedź (JSON).
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const feedback = document.getElementById('formFeedback');
  const submitBtn = document.getElementById('submitBtn');
  const togglePw = document.getElementById('togglePw');
  const pwInput = document.getElementById('password');
  const yearEl = document.getElementById('year');
  const csrfInput = document.getElementById('csrf_token');

  // ustaw rok
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // Pobierz jednorazowy CSRF token (opcjonalnie - endpoint może też wstawić token w HTML)
  // W tym przykładowym projekcie pobieramy token z /auth.php?csrf=1
  fetch('auth.php?csrf=1', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data && data.csrf_token) csrfInput.value = data.csrf_token;
    })
    .catch(() => {
      // jeśli nie uda się pobrać, nic - form użyje pustego tokenu i serwer odrzuci (bezpieczeństwo)
    });

  togglePw.addEventListener('click', () => {
    pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
    togglePw.textContent = pwInput.type === 'password' ? '👁️' : '🙈';
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback.textContent = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Ładowanie...';

    const formData = new FormData(form);

    try {
      const res = await fetch('auth.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      });

      if (!res.ok) throw new Error('Błąd sieci');
      const json = await res.json();

      if (json.success) {
        feedback.style.color = '#7cffb2';
        feedback.textContent = 'Zalogowano. Przekierowywanie…';
        // w realnym wdrożeniu przekieruj na dashboard
        setTimeout(() => { window.location.href = 'dashboard.php'; }, 700);
      } else {
        feedback.style.color = '#ff8080';
        feedback.textContent = json.message || 'Błąd logowania';
      }

    } catch (err) {
      feedback.style.color = '#ff8080';
      feedback.textContent = 'Błąd połączenia — spróbuj ponownie.';
      console.error(err);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Zaloguj';
    }
  });
});
