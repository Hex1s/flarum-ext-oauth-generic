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
    /* OAuth кнопка UNRIP */
    .LogInButton--oauth-generic {
      background-color: #4e73df !important;
      border-color: #4e73df !important;
    }
    .LogInButton--oauth-generic:hover {
      background-color: #2e59d9 !important;
      border-color: #2e59d9 !important;
    }
    
    /* Скрываем кнопку регистрации */
    .HeaderSecondary .Button--link[href*="register"] {
      display: none !important;
    }
    
    /* Скрываем автора в дискуссиях проектов и голосований */
    .DiscussionListItem[data-tags*="project-"] .DiscussionListItem-author,
    .DiscussionListItem[data-tags*="vote-"] .DiscussionListItem-author {
      display: none !important;
    }
    
    /* Скрываем аватар и имя автора в первом посте дискуссий проектов/голосований */
    .DiscussionPage[data-tags*="project-"] .PostUser,
    .DiscussionPage[data-tags*="vote-"] .PostUser {
      display: none !important;
    }
    
    /* Убираем отступ слева от контента, когда скрыт автор */
    .DiscussionPage[data-tags*="project-"] .Post-body,
    .DiscussionPage[data-tags*="vote-"] .Post-body {
      margin-left: 0 !important;
    }
    
    /* Скрываем метаинформацию о времени создания поста (опционально) */
    .DiscussionPage[data-tags*="project-"] .Post:first-child .PostMeta,
    .DiscussionPage[data-tags*="vote-"] .Post:first-child .PostMeta {
      display: none !important;
    }
    
    /* Альтернативный подход - скрываем весь блок пользователя в первом посте */
    .DiscussionPage[data-tags*="project-"] .Post:first-child .Post-user,
    .DiscussionPage[data-tags*="vote-"] .Post:first-child .Post-user {
      display: none !important;
    }
    
    /* Стилизуем первый пост как системное сообщение */
    .DiscussionPage[data-tags*="project-"] .Post:first-child,
    .DiscussionPage[data-tags*="vote-"] .Post:first-child {
      background-color: #f8f9fa !important;
      border-left: 4px solid #4e73df !important;
      padding: 15px !important;
      margin-bottom: 20px !important;
    }
    
    /* Добавляем иконку проекта/голосования */
    .DiscussionPage[data-tags*="project-"] .Post:first-child .Post-body::before {
      content: "🚀 ";
      font-size: 16px;
      margin-right: 8px;
    }
    
    .DiscussionPage[data-tags*="vote-"] .Post:first-child .Post-body::before {
      content: "🗳️ ";
      font-size: 16px;
      margin-right: 8px;
    }
  `;
  document.head.appendChild(style);
  
  console.log('🎨 UNRIP forum styling applied');
  
  // Дополнительная JavaScript логика для скрытия авторов
  function hideProjectAuthors() {
    // Скрываем авторов в списке дискуссий
    document.querySelectorAll('.DiscussionListItem').forEach(item => {
      const titleElement = item.querySelector('.DiscussionListItem-title');
      if (titleElement) {
        const href = titleElement.getAttribute('href') || '';
        // Проверяем, содержит ли URL теги проектов или голосований
        if (href.includes('/d/') && (
          item.textContent?.includes('Project:') || 
          item.textContent?.includes('Vote:') ||
          item.querySelector('[data-tag-slug*="project-"]') ||
          item.querySelector('[data-tag-slug*="vote-"]')
        )) {
          const authorElement = item.querySelector('.DiscussionListItem-author');
          if (authorElement) {
            authorElement.style.display = 'none';
          }
        }
      }
    });
    
    // Скрываем автора в первом посте дискуссии
    const firstPost = document.querySelector('.Post:first-child');
    if (firstPost && window.location.pathname.includes('/d/')) {
      // Проверяем теги дискуссии
      const tags = document.querySelectorAll('.DiscussionPage-tags .TagLabel');
      const hasProjectTag = Array.from(tags).some(tag => 
        tag.textContent?.includes('Project:') || 
        tag.textContent?.includes('project-') ||
        tag.textContent?.includes('Vote:') ||
        tag.textContent?.includes('vote-')
      );
      
      if (hasProjectTag) {
        const userElement = firstPost.querySelector('.Post-user');
        const postUser = firstPost.querySelector('.PostUser');
        if (userElement) userElement.style.display = 'none';
        if (postUser) postUser.style.display = 'none';
        
        // Добавляем системный стиль
        firstPost.style.backgroundColor = '#f8f9fa';
        firstPost.style.borderLeft = '4px solid #4e73df';
        firstPost.style.padding = '15px';
        firstPost.style.marginBottom = '20px';
      }
    }
  }
  
  // Запускаем при загрузке страницы
  hideProjectAuthors();
  
  // Запускаем при изменении страницы (SPA навигация)
  const observer = new MutationObserver(() => {
    hideProjectAuthors();
  });
  
  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
});