# DLInstagram

Instagram controller for DocLister

Пример использования:
```
[[DLInstagram? &token=`AAA....AAA` &display=`5`]]
```

Используемые параметры:

<table>
  <tr><th>Параметр</th><th>Обязательный</th><th>Значение по умолчанию</th><th>Описание</th></tr>
  <tr><td>token</td><td>Да</td><td>-</td><td>Токен, полученный по <a href="https://github.com/mnoskov/DLInstagram/blob/master/INSTRUCTION.md" target="_blank">инструкции</a></td></tr>
  <tr><td>cachetime</td><td>Нет</td><td>86400</td><td>Время кеширования результатов, в секундах</td></tr>
  <tr><td>fetchUserFields</td><td>Нет</td><td>id,media_count,<br>username</td><td>Поля, запрашиваемые для пользователя</td></tr>
  <tr><td>fetchMediaFields</td><td>Нет</td><td>caption,media_type,<br>media_url,permalink,<br>thumbnail_url,timestamp</td><td>Поля, запрашиваемые для медиаконтента</td></tr>
  <tr><td>tpl</td><td>Нет</td><td>@CODE: &lt;li>&lt;a href="[+url+]" target="_blank" rel="nofollow">&lt;img src="[+image+]" alt="[+e.caption+]">&lt;/a></td><td>Шаблон элемента</td></tr>
  <tr><td>ownerTPL</td><td>Нет</td><td>@CODE: &lt;ul>[+wrap+]&lt;/ul></td><td>Шаблон обертки</td></tr>
</table>

Изображения сохраняются в папку assets/images/instagram.<br>
В шаблонах доступны плейсхолдеры [+url+] - это permalink и [+image+] - это сохраненное изображение.
