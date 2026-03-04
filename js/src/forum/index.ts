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
    
    /* Убираем отступ слева в списке дискуссий когда скрыт автор */
    .DiscussionListItem-content {
      padding-left: 0 !important;
    }
    
    /* Скрываем автора в списке дискуссий для проектов/голосований */
    .DiscussionListItem .DiscussionListItem-author[href="/u/service-user"] {
      display: none !important;
    }
    
    /* Скрываем PostUser в первом посте дискуссий */
    .PostStream-item[data-index="0"] .PostUser {
      display: none !important;
    }
    
    /* Скрываем Post-header полностью для первого поста */
    .PostStream-item[data-index="0"] .Post-header {
      display: none !important;
    }
  `;
  document.head.appendChild(style);
  
  console.log('🎨 UNRIP forum styling applied');
  
  // Дополнительная JavaScript логика для скрытия авторов
  function hideProjectAuthors() {
    // Скрываем авторов в списке дискуссий для проектов/голосований
    document.querySelectorAll('.DiscussionListItem').forEach(item => {
      // Ищем теги проектов или голосований
      const tags = item.querySelectorAll('.TagLabel-name');
      const hasProjectOrVoteTag = Array.from(tags).some(tag => {
        const tagText = tag.textContent || '';
        return tagText.includes('Project:') || tagText.includes('pillars') || tagText.includes('vote-');
      });
      
      if (hasProjectOrVoteTag) {
        const authorElement = item.querySelector('.DiscussionListItem-author');
        if (authorElement) {
          console.log('🔧 Hiding author in discussion list:', item.querySelector('.DiscussionListItem-title')?.textContent);
          authorElement.style.display = 'none';
        }
      }
    });
    
    // Скрываем автора в первом посте дискуссии
    const firstPostItem = document.querySelector('.PostStream-item[data-index="0"]');
    if (firstPostItem) {
      // Проверяем теги дискуссии
      const tags = document.querySelectorAll('.TagLabel-name');
      const hasProjectTag = Array.from(tags).some(tag => {
        const tagText = tag.textContent || '';
        return tagText.includes('Project:') || tagText.includes('pillars') || tagText.includes('vote-');
      });
      
      if (hasProjectTag) {
        console.log('🔧 Hiding author in first post');
        
        // Скрываем весь header первого поста
        const postHeader = firstPostItem.querySelector('.Post-header');
        if (postHeader) {
          postHeader.style.display = 'none';
        }
        
        // Убираем стилизацию поста - оставляем как обычный пост
        
        // Добавляем иконку
        const postBody = firstPostItem.querySelector('.Post-body');
        if (postBody && !postBody.querySelector('.unrip-icon')) {
          const icon = document.createElement('span');
          icon.className = 'unrip-icon';
          icon.style.fontSize = '16px';
          icon.style.marginRight = '8px';
          icon.style.fontWeight = 'bold';
          
          // Определяем тип по тегам
          const isVoting = Array.from(tags).some(tag => 
            (tag.textContent || '').includes('vote-') || (tag.textContent || '').includes('Vote:')
          );
          icon.textContent = isVoting ? '🗳️ ' : '🚀 ';
          
          postBody.insertBefore(icon, postBody.firstChild);
        }
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