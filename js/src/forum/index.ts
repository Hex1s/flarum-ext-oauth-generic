import app from 'flarum/forum/app';

app.initializers.add('blt950/oauth-generic-forum', () => {
  console.log('UNRIP OAuth Generic extension loaded');
  
  // Добавляем кастомные стили для брендинга UNRIP
  const style = document.createElement('style');
  style.textContent = `
    /* Кастомизация кнопки входа через UNRIP */
    .LogInButton--oauth-generic {
      background-color: #4e73df !important;
      border-color: #4e73df !important;
    }
    
    .LogInButton--oauth-generic:hover {
      background-color: #2e59d9 !important;
      border-color: #2e59d9 !important;
    }
  `;
  document.head.appendChild(style);
});