
# Документация к API TM

---
## Список классов _Wss_:

1. **User** - работа с пользователями
    + [_**User**::isAuth_](#UserisAuth) - проверка авторизации по email и токену
    + [_**User**::auth_](#Userauth) - авторизация email и паролю
    + [_**User**::registration_](#Userregistration) - регистрация по email и паролю
    + [_**User**::sendConfirmation_](#UsersendConfirmation) - отправка кода подтверждения регистрации на E-mail
    + [_**User**::confirmationReg_](#UserconfirmationReg) - подтверждение регистрации кодом
    + [_**User**::addUser_](#UseraddUser) - добавление пользователя к текущей компании администратором
    + [_**User**::removeUser_](#UserremoveUser) - удаление пользователя администратором
    + [_**User**::addUserToDepartment_](#UseraddUserToDepartment) - привязка пользователя к департаменту
2. **Iblock** - работа с инфоблоками
    + [_**Iblock**::getNiches_](#IblockgetNiches) - получение списка Рыночных ниш (шаблонов)
    + [_**Iblock**::addDepartment_](#IblockaddDepartment) - создание департамента компании текущего пользователя
    + [_**Iblock**::getDepartments_](#IblockgetDepartments) - получение списка департаментов компании текущего пользователя
    + [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany) - получение свойств инфоблока событий компании текущего пользователя
    + [_**Iblock**::addEventCompany_](#IblockaddEventCompany) - добавления события компании текущего пользователя
    + [_**Iblock**::updateEventCompany_](#IblockupdateEventCompany) - обновление события компании текущего пользователя
    + [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany) - получение событий компании
    + [_**Iblock**::getEventsCompanyCounts_](#IblockgetEventsCompanyCounts) - получение событий компании в виде кол-ва и процентного соотношения
    + [_**Iblock**::getEventsCompanyCountsByTypes_](#IblockgetEventsCompanyCountsByTypes) - получение событий компании в виде кол-ва и процентного соотношения, сгруппированных по типам (общие, целевые, нецелевые, упущенные)
    + [_**Iblock**::getEventsCompanyCountsPrimary_](#IblockgetEventsCompanyCountsPrimary) - получение событий с первичным трафиком
    + [_**Iblock**::getEventsCompanyCountsSecondary_](#IblockgetEventsCompanyCountsSecondary) - получение событий со вторичным трафиком
    + [_**Iblock**::getEventsCompanyCountsMissed_](#IblockgetEventsCompanyCountsMissed) - получение событий с упущенным трафиком
    + [_**Iblock**::getReportEfficiency_](#IblockgetReportEfficiency) - получение отчета эффективности
    + [_**Iblock**::getReportEfficiencyByDate_](#IblockgetReportEfficiencyByDate) - получение отчета эффективности с группировкой по дате
    + [_**Iblock**::getForecastByDepartments_](#IblockgetForecastByDepartments) - прогноз продаж по департаментам
    + [_**Iblock**::getCheckingEventsInCrm_](#IblockgetCheckingEventsInCrm) - получение событий, которые не проверены и не занесены в CRM
    + [_**Iblock**::addCheckingInCrmEvent_](#IblockaddCheckingInCrmEvent) - добавление информации в событие о занесении в CRM
2. **Billing** - работа с платными подписками
    + [_**Billing**::check_](#Billingcheck) - проверка активности подписки текущей компании
    + [_**Billing**::add_](#Billingadd) - добавление подписки к компании

> Методы возвращают JSON массив, за исключением случаев, когда это указано отдельно.
> 
> Пример ответа:
```json
    {
      "status" : "success",
      "message": "Текст сообщения",
      "..."    : "..."
    }
```

> _**status**_ - содержит `success` в случае успеха, иначе `error` ошибка
> 
> _**message**_ - содержит текстовый ответ
> 
>  ⚠️**ВАЖНО**⚠️
> 
> Для корректной работы методов, в большинстве случаев необходимо передавать `email`(login) и `token` в параметрах по умолчанию для проверки авторизации, хранящиеся обычно в Cookies или LocalStorage (выбор клиента), за исключением таких методов как _**User**::auth_ и _**User**::registration_, где `token` еще неизвестен.

* _**User**_ - работа с пользователями
    * <a id="UserisAuth"></a>_isAuth_ - проверка авторизации по email и токену
       ```http request
       /api/user/?isAuth&email='email'&token='token'
       ```
      > **Обязательные передаваемые параметры:**
      > * _email_ - email (может принимать системный LOGIN для администраторов)
      > * _token_ - хеш сессии
      
      > **В случае успеха, вернет поля:**
      > * _fields_ - Поля пользователя
        
    * <a id="Userauth"></a>_auth_ - авторизация email и паролю
       ```http request
       /api/user/?auth&email='email'&password='password'
       ```
      > **Обязательные передаваемые параметры:**
      > * _email_ - Email
      > * _password_ - пароль
      
      > **В случае успеха, вернет поля:**
      > * _token_ - Специальный хеш от пароля
      
    * <a id="Userregistration"></a>_registration_ - регистрация по email и паролю
       ```http request
       /api/user/?registration&email='email'&company_name='Компания'&password1='pass'&password2='pass'
       ```
      > **Обязательные передаваемые параметры:**
      > * _email_ - Email
      > * _niche_id_ - id ниши, будет создан инфоблок событий компании, согласно выбранной нише
      > * _company_name_ - название компании
      > * _password1_ - пароль
      > * _password2_ - подтверждение пароля
      >
      > **Дополнительные передаваемые параметры:**
      > * _name_ - имя
      > * _last_name_ - фамилия
      
      > **В случае успеха, вернет поля:**
      > * _token_ - Специальный хеш от пароля
      > * _company_id_ - ID созданной компании

    * <a id="UsersendConfirmation"></a>_sendConfirmation_ - отправка кода подтверждения регистрации на E-mail
       ```http request
       /api/user/?sendConfirmation&email='email'
       ```
      > **Обязательные передаваемые параметры:**
      > * _email_ - принимает EMAIL, LOGIN или ID пользователя
      > 
      >
      >  ⚠️**ВАЖНО**⚠️
      >
      > При регистрации пользователя методом _**User**::registration_, код подтверждения отправляется автоматически.
    * <a id="UserconfirmationReg"></a>_confirmationReg_ - подтверждение регистрации кодом
      ```http request
       /api/user/?confirmationReg&email='email'&code='код из письма'
       ```
      > **Обязательные передаваемые параметры:**
      > * _email_ - принимает EMAIL, LOGIN или ID пользователя
      > * _code_ - код из письма подтверждения регистрации

    * <a id="UseraddUser"></a>_addUser_ - добавление/обновление пользователей к текущей компании администратором ⚠️**тестирование**⚠️
        ```http request
        /api/user/?addUser&user_email=email@mail.com&user_type=admin&user_name=Василий
        ```
      > **Обязательные передаваемые параметры:**
      > * _user_name_ - имя (необязательно)
      > * _user_email_ - E-mail
      > 
      > **Дополнительные передаваемые параметры:**
      > * _user_type_ - тип пользователя, по умолчанию `user`
      > * _user_id_ - id пользователя, будет обновлен пользователь
      > 
      > 
      >     Типы:
      > 
      >     admin - администратор (добавление событий, пользователей)
      >     controller - контроллер (добавление событий)
      >     marketer - маркетолог (просмотр всех событий в пределах компании)
      >     rop - руководитель отдела продаж (просмотр всех событий в пределах департамента)
      >     user - сотрудник (просмотр только своих событий)
      >
      > 
      > При успешном добавлении пользователя, ему будет отправлено письмо на указанный E-mail адрес с логином и паролем.
    * <a id="UserremoveUser"></a>_removeUser_ - удаление пользователя администратором ⚠️**тестирование**⚠️
      ```http request
        /api/user/?removeUser&user_id=1
      ```
      > **Обязательные передаваемые параметры:**
      > * _user_id_ - id пользователя
      > 
      > Пользователь будет деактивирован в системе. Повторная регистрация будет невозможной, либо возможна, но только через главного администратора системы.
  * <a id="UseraddUserToDepartment"></a>_addUserToDepartment_ - привязка пользователя к департаменту администратором ⚠️**тестирование**⚠️
    ```http request
      /api/user/?addUserToDepartment&user_id=1&department=2
    ```
    > **Обязательные передаваемые параметры:**
    > * _user_id_ - id пользователя
    > * _department_ - id департамента, полученный методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany) или [_**Iblock**::getDepartments_](#IblockgetDepartments)
    >
    > **Дополнительные передаваемые параметры:**
    > * _user_type_ - тип пользователя (роль). По умолчанию ставится роль, которая была указана при первичном добавлении пользователя с помощью метода `addUser` (если не был указан, то `user`).
    >
    > 
    >     Типы:
    >
    >     rop - руководитель отдела продаж (просмотр всех событий в пределах департамента)
    >     user - сотрудник (просмотр только своих событий)
    >
    > Если пользователь уже был привязан к одному из департаментов, то он будет отвязан от него и привязан к указанному в параметре. Если указать в параметре ноль `department=0`, тогда пользователь будет просто отвязан от своего департамента. 
    > 
    > Тип роли может быть только `rop` или `user` т.к.другие роли в департаменте бессмысленны.

  * <a id="UsergetList"></a>_getList_ - список сотрудников текущей компании ⚠️**тестирование**⚠️
    ```http request
      /api/user/?getList
    ```
* **_Iblock_** - работа с инфоблоками
    * <a id="IblockgetNiches"></a>_getNiches_ - получение списка Рыночных ниш (шаблонов)
      ```http request
      /api/iblock/?getNiches
      ```
      > **В случае успеха, вернет поля:**
      > * _list_ - Список рыночных ниш
    * <a id="IblockaddDepartment"></a>_addDepartment_ - создание/изменение департаментов компании текущего пользователя
      ```http request
      /api/iblock/?addDepartment&names[]=Департамент1&names[321]=Департамент2
      ```
      > **Обязательные передаваемые параметры:**
      > * _names_ - Названия департаментов в виде массива, где цифровой ключ является id департамента, а пустой ключ  для новых департаментов, также если id не будет найден в департаментах, то будет создан новый. Если не передать существующий департамент в запросе, то он будет удален (деактивирован).

    * <a id="IblockgetDepartments"></a>_getDepartments_ - получение списка департаментов компании текущего пользователя
      ```http request
      /api/iblock/?getDepartments
      ```
      > **В случае успеха, вернет поля:**
      > * _list_ - Список департаментов компании текущего пользователя со списком привязанных сотрудников
      > 
      > Если пользователь привязан к департаменту, то он увидит только свой департамент, к таким относятся обычные сотрудники и роп.
    * <a id="IblockgetPropsByEventIblockCompany"></a> _getPropsByEventIblockCompany_ - получение свойств инфоблока событий компании текущего пользователя
      ```http request
      /api/iblock/?getPropsByEventIblockCompany
      ```
      > **В случае успеха, вернет список свойств с их полями:**
      > * _prop1_ - Свойство 1
      > * _prop2_ - Свойство 2
      > * _........._ - Свойство ...
      > * _event_id_ - id события (вернет свойства конкретного события в виде модели Vue)
      ```json
          {
              "code_property": {
                "id": 1,
                "code": "code_property",
                "name": "Название свойства",
                "type": "LIST", // тип свойства STRING - строка, LIST - список, DATE_TIME - Дата и время, BOOLEAN - да/нет
                "required": true, // если `true`, то свойство обязательно для заполнения
                "values": [ // список значений свойства для типа LIST
                    {
                        "id": 2, // совпадает с value (служебное)
                        "value": 2,
                        "label": "Значение свойства"
                    }
                ]
             }
         }
      ```

    * <a id="IblockupdateCompany"></a>_updateCompany_ - изменение компании текущего пользователя
      ```http request
      /api/iblock/?updateCompany&name=название
      ```
        > **Передаваемые параметры:**
        > * _name_ - название компании

    * <a id="IblockgetCompanyName"></a>_getCompanyName_ - название компании текущего пользователя
      ```http request
      /api/iblock/?getCompanyName
      ```
        > **Передаваемые параметры:**
        > * _name_ - название компании
      
    * <a id="IblockaddEventCompany"></a>_addEventCompany_ - добавления события компании текущего пользователя
      ```http request
      /api/iblock/?addEventCompany&prop1=val1&prop2=val2&prop3=val3 ...
      ```
        > **Передаваемые параметры:**
        > * _prop1,prop2,prop3_ - свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
        > * _date_ - дата события, внутри метода склеивается со свойством `date_time`(время события)
        > 
        > Обязательность заполнения свойства устанавливается в настройках инфоблока События компании

    * <a id="IblockupdateEventCompany"></a>_updateEventCompany_ - обновление события компании текущего пользователя
      ```http request
      /api/iblock/?updateEventCompany&event_id=1&prop1=val1&prop2=val2&prop3=val3 ...
      ```
      > **Передаваемые параметры:**
      > * _event_id_ - id события (обязательный), полученный методом [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany)
      > * _prop1,prop2,prop3_ - свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany), будут изменены те, которые переданы, другие останутся неизмененными
      > * _date_ - дата события, внутри метода склеивается со свойством `date_time`(время события)
      > * _delete_ - принимает значение `y`, если необходимо удалить событие
      >
      > Обязательность заполнения свойства устанавливается в настройках инфоблока События компании
    * <a id="IblockgetEventsCompany"></a> _getEventsCompany_ - получение событий компании
      ```http request
      /api/iblock/?getEventsCompany&sort=date
      ```
      > **Передаваемые параметры:**
      > * _date_from_ - дата от, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...)
      > * _date_to_ - дата до, в любом корректном формате
      > * _date_ - конкретная дата, в любом корректном формате
      > 
      > Также можно указать только `date_from` (`date_to` будет игнорироваться) и значение-константу вида:
      > 
      >     day - за 24 часа
      >     cur_day - текущий день (с 00:00 до текущего времени)
      >     last_day - прошлый день (с 00:00 до 23:59)
      >     week - 7 дней
      >     cur_week - текущая неделя (с понедельника)
      >     last_week - прошлая неделя (с понедельника по воскресенье)
      >     month - 30 дней
      >     cur_month - текущий месяц (с 1 числа месяца)
      >     last_month - прошлый месяц (с 1 по последнее число месяца)
      >     year - 365 дней
      >     cur_year - текущий год (с 1 января)
      >     last_year - прошлый год (с 1 января по 31 декабря)
      > 
      > например:
      > 
      >     date_from=month
      > 
      > * _sort_ - сортировка (по умолчанию сортировка событий по дате от нового к старому)
      >
      > Сортировать можно по любому свойству, полученному в текущем методе, например:
      > 
      >     sort=date_time-asc - код свойства и направление сортировки необходимо указывать через тире `-`
      > 
      > * _page_size_ - кол-во выводимых событий для постраничной навигации
      > * _page_num_ - номер страницы для постраничной навигации
      > * _department_ - id департамента, полученный методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany) или [_**Iblock**::getDepartments_](#IblockgetDepartments)
      > 
      > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
      
      > **В случае успеха, вернет поля:**
      > * _list_ - Список событий компании текущего пользователя

    * <a id="IblockgetEventsCompanyCounts"></a> _getEventsCompanyCounts_ - получение событий компании в виде кол-ва и процентного соотношения
      ```http request
      /api/iblock/?getEventsCompanyCounts&type=targeted&group_by=result
      ```
      > **Передаваемые параметры:**
      > * _type_ - тип получаемых данных. Если не указать, то будут получены общие данные
      > 
      > Типы:
      > 
      >     targeted - целевые события
      >     no_targeted - не целевые события
      >     missed - упущенные события
      >     no_missed - не упущенные события
      >     primary - первичные события
      >     secondary - вторичные события
      > 
      > Данные типы регулируются с помощью XML_ID значений свойства `event_type` в настройках инфоблока событий, т.е. XML_ID должен быть вида `prop1_targeted` , `prop2`_ или `prop3_targeted_primary` (разных вариаций)
      >
      > **Дополнительные параметры:**
      > * _date_from_ - дата от (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
      > * _date_to_ - дата до (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
      > * _group_by_ - свойство, по которому надо сгруппировать данные, вернет результат в ключе `groups`
      > * _group_by_date_ - группировка по дате на каждый день периода, принимает значение `y`, вернет дополнительный массив `dates` дат на каждый день периода
      > 
      > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
        
      Пример целевых событий для цифровых блоков с группировкой по результату:
    
      ```http request
      /api/iblock/?getEventsCompanyCounts&type=targeted&group_by=result&date_from=01.01.2022&date_to=07.01.2022
      ```

      ```json
          {
              "score": { // статистика за выбранный период
                "name": "Целевой", // название типа события
                "value": 25,
                "unit": "" // абсолютное кол-во событий
              },
              "trend": { // статистика за предыдущий период в процентах
                "value": 100,
                "unit": "%"
              },
              "period": 30, // период в днях, за который отображаются события
              "groups": [ // группировка событий по указанному свойству в параметре group_by
                {
                  "score": {
                    "name": "Договор" // текстовое значение свойства
                    "value": 36, // кол-во в процентах от общего значения
                    "unit": "%"
                  },
                  "trend": { // статистика за предыдущий период в процентах
                    "value": 100,
                    "unit": "%"
                  }
                },
                {
                  "score": {
                    "name": "Презентация"
                    "value": 18,
                    "unit": "%"
                  },
                  "trend": {
                    "value": 100,
                    "unit": "%"
                  }
                }
              ]
         }
      ```
        
      Пример для графиков с группировкой по департаментам и дате:

      ```http request
      /api/iblock/?getEventsCompanyCounts&type=targeted&group_by=department&group_by_date=y&date_from=01.01.2022&date_to=07.01.2022
      ```

      ```json
          {
              "score": { // статистика за выбранный период
                "name": "Целевой", // название типа события
                "value": 25,
                "unit": "" // абсолютное кол-во событий
              },
              "trend": { // статистика за предыдущий период в процентах
                "value": 100,
                "unit": "%"
              },
              "period": 30, // период в днях, за который отображаются события
              "groups": [ // группировка событий по указанному свойству в параметре group_by
                {
                  "score": {
                    "name": "Департамент 1" // текстовое значение свойства
                    "value": 36, // кол-во в процентах от общего значения
                    "unit": "%",
                    "data":  [10, 15, 0, 41, 52] // число событий на каждую дату при группировке group_by_date, каждая цифра соответствует порядку дат из массива dates
                  }
                },
                {
                  "score": {
                    "name": "Департамент 2"
                    "value": 18,
                    "unit": "%",
                    "data":  [10, 15, 0, 41, 52]
                  }
                }
              ],
              "dates":  ["01 Янв", "02 Янв", "03 Янв", "04 Янв", "05 Янв"] // массив при активной группировке по дате
         }
      ```
    * <a id="IblockgetEventsCompanyCountsByTypes"></a> _getEventsCompanyCountsByTypes_ - получение событий компании в виде кол-ва и процентного соотношения, сгруппированных по типам
      > **Передаваемые параметры:**
      > * _date_from_ - дата от (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
      > * _date_to_ - дата до (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
      >
      > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
    * <a id="IblockgetEventsCompanyCountsPrimary"></a> _getEventsCompanyCountsPrimary_ - получение событий с первичным трафиком ⚠️**на тестировании**⚠️
        > **Передаваемые параметры:**
        > * _date_from_ - дата от (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        > * _date_to_ - дата до (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        >
        > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
    * <a id="IblockgetEventsCompanyCountsSecondary"></a> _getEventsCompanyCountsSecondary_ - получение событий со вторичным трафиком ⚠️**на тестировании**⚠️
        > **Передаваемые параметры:**
        > * _date_from_ - дата от (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        > * _date_to_ - дата до (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        >
        > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
    * <a id="IblockgetEventsCompanyCountsMissed"></a> _getEventsCompanyCountsMissed_ - получение событий с упущенным трафиком ⚠️**на тестировании**⚠️
        > **Передаваемые параметры:**
        > * _date_from_ - дата от (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        > * _date_to_ - дата до (аналогично методу [_**Iblock**::getEventsCompany_](#IblockgetEventsCompany))
        >
        > Также можно передавать для фильтрации другие свойства, полученные методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany)
  * <a id="IblockgetReportEfficiency"></a> _getReportEfficiency_ - получение отчета эффективности
    ```http request
    /api/iblock/?getReportEfficiency&type=primary&group_by=user
    ```
    > **Передаваемые параметры:**
    > * _date_from_ - дата от, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...)
    > * _date_to_ - дата до, в любом корректном формате
    > * _date_ - конкретная дата, в любом корректном формате
    > * _type_ - тип получаемых данных (`primary`, `secondary`)
    > * _department_ - id департамента, полученный методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany) или [_**Iblock**::getDepartments_](#IblockgetDepartments)
    
  * <a id="IblockgetReportEfficiencyByDate"></a> _getReportEfficiencyByDate_ - получение отчета эффективности с группировкой по дате
    ```http request
    /api/iblock/?getReportEfficiencyByDate&type=primary&group_by=user
    ```
    > **Передаваемые параметры:**
    > * _date_from_ - дата от, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...)
    > * _date_to_ - дата до, в любом корректном формате
    > * _date_ - конкретная дата, в любом корректном формате
    > * _type_ - тип получаемых данных (`primary`, `secondary`)
    > * _department_ - id департамента, полученный методом [_**Iblock**::getPropsByEventIblockCompany_](#IblockgetPropsByEventIblockCompany) или [_**Iblock**::getDepartments_](#IblockgetDepartments)
     
  * <a id="IblockgetForecastByDepartments"></a> _getForecastByDepartments_ - прогноз продаж по департаментам
    ```http request
    /api/iblock/?getForecastByDepartments
    ```
     
  * <a id="IblockgetCheckingEventsInCrm"></a> _getCheckingEventsInCrm_ - получение событий, которые не проверены и не занесены в CRM
    ```http request
    /api/iblock/?getCheckingEventsInCrm
    ```
     
  * <a id="IblockaddCheckingInCrmEvent"></a> _addCheckingInCrmEvent_ - 
    ```http request
    /api/iblock/?addCheckingInCrmEvent&event_id=12345&date_check_crm=01.01.2022&check_crm=y&check_number=3
    ```
    > **Передаваемые параметры:**
    > * _event_id_ - ID события
    > * _date_check_crm_ - дата, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...), если не передать, то установится текущая
    > * _check_crm_ - признак проверки `y` или `n`
    > * _check_number_ - количество проверок
    

    
  
* **_Billing_** - работа с подписками
    * <a id="Billingcheck"></a>_check_ - проверка активной подписки текущей компании
      ```http request
      /api/billing/?check
      ```
    * <a id="Billingadd"></a>_add_ - добавление подписки (только для администраторов системы)
      ```http request
      /api/billing/?add
      ```
      > **Передаваемые параметры:**
      > * _company_id_ - id компании
      > * _date_from_ - дата от, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...)
      > * _date_to_ - дата до, в любом корректном формате (_01.01.2021_,_01-01-2021_,_2021-12-31_,...)
      > * _period_ - количество дней подписки (будут игнорироваться параметры _date_from_ и _date_to_)
      > * _sum_ - сумма
      >
