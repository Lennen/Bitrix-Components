<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arParams */
/** @var array $arResult */
?>
<?if (!empty($arResult['USERS'])) : ?>
    <div class="birthday"></div>
    <div class="new-employees">
        <div class="new-employees-top">
            <h4 class="new-employees__title"><?=$arParams['HEADER_LABEL']?></h4>
        </div>
        <div class="new-employees-main">
            <div class="new-employees-rows">
                <? foreach ($arResult['USERS'] as $arUser): ?>
                    <div class="new-employees-item">
                        <a href="#/user/:id/" class="new-employees-item__name"><?=$arUser['FORMAT_NAME']?></a>
                        <div class="new-employees-item__position"><?= $arUser['WORK_POSITION'] ?></div>
                    </div>
                <? endforeach ?>
            </div>
        </div>
    </div>
<?endif;?>