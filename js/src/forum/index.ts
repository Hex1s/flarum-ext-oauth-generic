import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';

app.initializers.add('blt950/oauth-generic-forum', () => {
  console.log('🚀 UNRIP OAuth Generic extension loaded!');
  
  // Скрываем кнопку регистрации
  extend(HeaderSecondary.prototype, 'items', function (items) {
    console.log('🔧 Hiding signUp button');
    items.remove('signUp');
  });
  
  // Добавляем кастомные стили
  const style = document.createElement('style');
  style.textContent = `
    .LogInButton--oauth-generic {
      background-color: #4e73df !important;
      border-color: #4e73df !important;
    }
    .LogInButton--oauth-generic:hover {
      background-color: #2e59d9 !important;
      border-color: #2e59d9 !important;
    }
    /* Тест - скрываем кнопку регистрации через CSS */
    .HeaderSecondary .Button--link[href*="register"] {
      display: none !important;
    }
  `;
  document.head.appendChild(style);
});