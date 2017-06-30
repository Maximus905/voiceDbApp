<?php

namespace App\Controllers;

use App\Components\Ip;
use App\Components\IpTools;
use App\Components\Parser;
use App\Components\RequestExt;
use App\Components\Timer;
use App\Components\UrlExt;
use App\Models\Address;
use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Models\City;
use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Module;
use App\Models\ModuleItem;
use App\Models\Network;
use App\Models\Office;
use App\Models\OfficeStatus;
use App\Models\Platform;
use App\Models\PlatformItem;
use App\Models\Region;
use App\Models\Software;
use App\Models\SoftwareItem;
use App\Models\Vendor;
use App\Models\VPortType;
use App\Models\Vrf;
use App\ViewModels\GeoDev_View;
use App\ViewModels\GeoDevModulePort_View;
use App\ViewModels\ModuleItem_View;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;
use T4\Dbal\QueryBuilder;
use T4\Http\Request;
use T4\Mvc\Controller;

class Admin extends Controller
{
    use DebugTrait;

    public function actionDefault()
    {

    }
    public function actionRegions()
    {
        $this->data->regions = Region::findAll(['order' => 'title']);
        $this->data->activeLink->dictionary = true;
    }
    public function actionAddRegion($region = null)
    {
        try {
            Region::getDbConnection()->beginTransaction();
            if (!empty($region)) {
                if (!empty(trim($region['many']))) {
                    $pattern = '~[\n\r]~';
                    $regsInString = preg_replace($pattern, ',', trim($region['many']));
                    $regInArray = explode(',', $regsInString);

                    try {
                        foreach ($regInArray as $region) {
                            (new Region())
                                ->fill(['title' => trim($region)])
                                ->save();
                        }
                        $this->data->result = 'Регионы добавлены';
                    } catch (MultiException $e) {
                        $e->prepend(new Exception('Ошибка пакетного ввода'));
                        throw $e;
                    }
                } elseif (!empty($region['one'])) {
                    $res = (new Region())
                        ->fill(['title' => $region['one']])
                        ->save();
                    $this->data->result = 'Регион добавлен';
                } else {
                    $this->data->result = 'Нет данных';
                }
            }
            Region::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditRegion($region)
    {
        try {
            Region::getDbConnection()->beginTransaction();
            if (false === $item = Region::findByPK($region['id'])) {
                throw new Exception('Неверные данные');
            }
            if ($item->title != $region->title && false !== City::findByColumn('title', $region->title)) {
                throw new Exception('Регион с таким именем существует');
            }
            $item->fill([
                'title' => $region->title
            ]);
            $item->save();
            $this->data->result = 'Регион изменен';

            Region::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelRegion($id)
    {
        try {
            Region::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = Region::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка наличия городов в этом регионе
            if ($item->cities->count() > 0) {
                throw new Exception('Удаление невозможно. Регион используется.');
            }
            $item->delete();
            $this->data->result = 'Регион "' . $item->title .  '" удален';

            Region::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionCities()
    {
        $asc = function (City $city_1, City $city_2) {
            return strnatcmp(mb_strtolower($city_1->region->title), mb_strtolower($city_2->region->title));
        };

        $this->data->cities = City::findAll()->uasort($asc);
        $this->data->activeLink->dictionary = true;
    }

    public function actionAddCity($city)
    {
        try {
            City::getDbConnection()->beginTransaction();
            if (!is_numeric($city['regId'])) {
                throw new Exception('Регион не выбран');
            }
            if (false === $region = Region::findByPK($city['regId'])) {
                throw new Exception('Регион не найден');
            }
            (new City())
                ->fill([
                    'title' => $city['title'],
                    'region' => $region
                ])
                ->save();

            City::getDbConnection()->commitTransaction();

        } catch (MultiException $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditCity($city)
    {
        try {
            City::getDbConnection()->beginTransaction();

            if (false === $editedCity = City::findByPK($city['id'])) {
                throw new Exception('Неверные данные');
            }
            if ($editedCity->title != $city->title && false !== City::findByColumn('title', $city->title)) {
                throw new Exception('Город с таким именем существует');
            }
            $editedCity->title = $city->title;
            $editedCity->region = Region::findByPK($city['regId']);
            $editedCity->save();

            City::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelCity($id = null)
    {
        try {
            City::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = City::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка наличия адресов в этом регионе
            if ($item->addresses->count() > 0) {
                throw new Exception('Удаление невозможно. Город используется.');
            }
            $item->delete();
            $this->data->result = 'Город  "' . $item->title .  '" удален';

            City::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            City::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    /**
     * action вывода всех имеющихся статусов
     */
    public function actionOfficeStatuses()
    {
        $this->data->statuses = OfficeStatus::findAll(['order' => 'title']);
        $this->data->activeLink->dictionary = true;
    }
    /**
     * action добавления нового статуса.
     *
     * @param array $status новые статусы ['many'] с ',' в качестве разделителя
     * или один новый статус ['one']
     */
    public function actionAddStatus($status = null)
    {
        try {
            OfficeStatus::getDbConnection()->beginTransaction();

            (new OfficeStatus())
                ->fill($status)
                ->save();

            OfficeStatus::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditStatus($status)
    {
        try {
            OfficeStatus::getDbConnection()->beginTransaction();
            if (false === $editedStatus = OfficeStatus::findByPK($status['id'])) {
                throw new Exception('Неверные данные');
            }
            if ($editedStatus->title != $status->title && false !== OfficeStatus::findByColumn('title', $status->title)) {
                throw new Exception('Такой статус уже существует');
            }
            $editedStatus
                ->fill([
                    'title' => $status->title
                ])
                ->save();

            OfficeStatus::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelStatus($id = null)
    {
        try {
            OfficeStatus::getDbConnection()->beginTransaction();
            if (false === $status = OfficeStatus::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            if ($status->offices->count() > 0 ) {
                throw new Exception('Удаление невозможно. Данный статус используется');
            }
            $status->delete();
            $this->data->result = 'Статус "' . $status->title . '" удален.';

            OfficeStatus::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            OfficeStatus::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }

    }

    public function actionOffices()
    {
        $asc = function (Office $office_1, Office $office_2) {
            return (0 != $compareRes = strnatcmp(mb_strtolower($office_1->address->city->region->title), mb_strtolower($office_2->address->city->region->title))) ? $compareRes : 1;
        };

        $this->data->offices = Office::findAll()->uasort($asc);
        $this->data->activeLink->offices = true;
    }

    /**
     * @param Std $data
     * В случае $data->many формат записи для офиса : регион; город; адрес; офис; статус
     * @throws Exception
     */
    public function actionAddOffice($data)
    {
        //если поле пакетного ввода не пустое, то берем данные оттуда
        if (!empty(trim($data->many))) {
            try {
                Office::getDbConnection()->beginTransaction();

                $officeCollection = Parser::lotusTerritory(trim($data->many));
                if (false === $officeCollection) {
                    throw new Exception('Ошибка данных пакетного ввода');
                }

                foreach ($officeCollection as $item) {
                    //Region
                    $region = Region::findByTitle($item->region);
                    if (false === $region && isset($data->addNewRegion)) {
                        $region = (new Region())
                            ->fill([
                                'title' => $item->region
                            ])
                            ->save();
                    }

                    //City
                    $city = City::findByTitle($item->city);
                    if (false === $city && isset($data->addNewCity)) {
                        $city = (new City())
                            ->fill([
                                'title' => $item->city,
                                'region' => $region
                            ])
                            ->save();
                    }

                    //Address
                    $address = (new Address())
                        ->fill([
                            'address' => $item->address,
                            'city' => $city
                        ])
                        ->save();

                    //статус (берем из формы)
                    $status = OfficeStatus::findByPK($data->statusId);


                    //собираем офис
                    (new Office())
                        ->fill([
                            'title' => $item->office,
                            'lotusId' => $item->lotusId,
                            'address' => $address,
                            'status' => $status
                        ])
                        ->save();
                }
                Office::getDbConnection()->commitTransaction();

            } catch (MultiException $e) {
                Office::getDbConnection()->rollbackTransaction();
                $e->prepend(new Exception('Ошибка пакетного ввода'));
                $this->data->errors = $e;
            } catch (Exception $e) {
                Office::getDbConnection()->rollbackTransaction();
                $errors = (new MultiException())
                    ->add(
                        new Exception('Ошибка пакетного ввода')
                    );
                $this->data->errors = $errors->add($e);
            }
            return;
        }

        //если поле пакетного ввода пустое, то берем поля формы
        try {
            Office::getDbConnection()->beginTransaction();
            if (!is_numeric($data->regionId)) {
                throw new Exception('Регион не выбран');
            }
            if (!is_numeric($data->cityId)) {
                throw new Exception('Город не выбран');
            }
            if (!is_numeric($data->statusId)) {
                throw new Exception('Статус не выбран');
            }

            //создаем объект адреса
            $address = (new Address())
                ->fill([
                    'address' => $data->address,
                    'city' => City::findByPK($data->cityId)
                ])
                ->save();
            //собираем офис
            (new Office())
                ->fill([
                    'title' => $data->title,
                    'lotusId' => $data->lotusId,
                    'address' => $address,
                    'status' => OfficeStatus::findByPK($data->statusId)
                ])
                ->save();

            Office::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditOffice($data)
    {
        try {
            Office::getDbConnection()->beginTransaction();
            /**
             * @var Office $office
             */
            $office = Office::findByLotusId($data->curLotusId);
            $oldAddress = $office->address;
            $city = City::findByPK($data->cityId);
            $status = OfficeStatus::findByPK($data->statusId);
            if ($office->lotusId != trim($data->lotusId) && false !== Office::findByColumn('lotusId', trim($data->lotusId))) {
                throw new Exception('Офис с данным Lotus ID существует');
            }
            if ($office->title != trim($data->title) && false !== Office::findByColumn('title', trim($data->title))) {
                throw new Exception('Офис с таким названием существует');
            }
            if ($oldAddress->address != $data->address) {
                $newAddress = (new Address())
                    ->fill([
                        'address' => $data->address,
                        'city' => $city
                    ])
                    ->save();
                $office->fill(['address' => $newAddress])->save();
                $oldAddress->delete();
            }
            //собираем офис с изменениями
            $office
                ->fill([
                    'title' =>$data->title,
                    'status' => $status,
                    'lotusId' => $data->lotusId,
                    'comment' => $data->comment
                ])
                ->save();

            Office::getDbConnection()->commitTransaction();

        } catch (MultiException $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelOffice($id = null)
    {
        try {
            Office::getDbConnection()->beginTransaction();
            if (false === $office = Office::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            if ($office->appliances->count() > 0 ) {
                throw new Exception('Удаление невозможно. Данный офис используется');
            }
            $office->delete();
            $office->address->delete();
            $this->data->result = 'Офис "' . $office->title . '" удален.';

            Office::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Office::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDevparts()
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->platforms = Platform::findAll(['order' => 'title']);
        $this->data->software = Software::findAll(['order' => 'title']);
        $this->data->modules = Module::findAll(['order' => 'title']);
        $this->data->applianceTypes = ApplianceType::findAll(['order' => 'type']);

        $this->data->settings->activeTab = 'platforms';
        $this->data->activeLink->dictionary = true;
    }

    public function actionAddApplianceType($applianceType)
    {
        try {
            ApplianceType::getDbConnection()->beginTransaction();
            (new ApplianceType())
                ->fill($applianceType)
                ->save();

            ApplianceType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditApplianceType($applianceType)
    {
        try {
            ApplianceType::getDbConnection()->beginTransaction();

            if (false === $editedType = ApplianceType::findByPK($applianceType->id)) {
                throw new Exception('Неверные данные');
            }
            if ($editedType->type != $applianceType->type && false !== ApplianceType::findByColumn('type', $applianceType->type)) {
                throw new Exception('Такой тип существует');
            }
            $editedType
                ->fill([
                    'type' => $applianceType->type
                ])
                ->save();

            ApplianceType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelApplianceType($id)
    {
        try {
            ApplianceType::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = ApplianceType::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка использования данного объекта
            if ($item->appliances->count() > 0) {
                throw new Exception('Удаление невозможно. Данный тип(роль) используется.');
            }
            $item->delete();
            $this->data->result = 'Тип(роль) "' . $item->type .  '" удален';

            ApplianceType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            ApplianceType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionAddPlatform($platform)
    {
        try {
            Platform::getDbConnection()->beginTransaction();
            if (!is_numeric($platform->vendorId)) {
                throw new Exception('Производитель не выбран');
            }
            if (false === $vendor = Vendor::findByPK($platform->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            (new Platform())
                ->fill([
                    'title' => $platform->title,
                    'vendor' => $vendor
                ])
                ->save();
            Platform::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditPlatform($platform)
    {
        try {
            Platform::getDbConnection()->beginTransaction();

            if (false === $updatedPlatform = Platform::findByPK($platform->id)) {
                throw new Exception('Неверные данные');
            }
            if (false === $vendor = Vendor::findByPK($platform->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            if ($platform->title != $updatedPlatform->title && false !== Platform::findByColumn('title', $platform->title)) {
                throw new Exception('Такая платформа существует');
            }
            $updatedPlatform
                ->fill([
                    'title' => $platform->title,
                    'vendor' => $vendor
                ])
                ->save();

            Platform::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelPlatform($id)
    {
        try {
            Platform::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = Platform::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка использования данного объекта
            if ($item->platformItems->count() > 0) {
                throw new Exception('Удаление невозможно. Данная платформа используется.');
            }
            $item->delete();
            $this->data->result = 'Платформа "' . $item->title .  '" удалена';

            Platform::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Platform::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionAddModule($module)
    {
        try {
            Module::getDbConnection()->beginTransaction();
            if (!is_numeric($module->vendorId)) {
                throw new Exception('Производитель не выбран');
            }
            if (false === $vendor = Vendor::findByPK($module->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            (new Module())
                ->fill([
                    'title' => $module->title,
                    'vendor' => $vendor
                ])
                ->save();
            Module::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditModule($module)
    {
        try {
            Module::getDbConnection()->beginTransaction();

            if (false === $updatedModule = Module::findByPK($module->id)) {
                throw new Exception('Неверные данные');
            }
            if (false === $vendor = Vendor::findByPK($module->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            if ($module->title != $updatedModule->title && false !== Module::findByColumn('title', $module->title)) {
                throw new Exception('Такой модуль существует');
            }
            $updatedModule
                ->fill([
                    'title' => $module->title,
                    'vendor' => $vendor
                ])
                ->save();

            Module::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelModule($id)
    {
        try {
            Module::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = Module::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка использования данного объекта
            if ($item->moduleItems->count() > 0) {
                throw new Exception('Удаление невозможно. Данный модуль используется.');
            }
            $item->delete();
            $this->data->result = 'Модуль "' . $item->title .  '" удален';

            Module::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Module::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionAddSoftware($software)
    {
        try {
            Software::getDbConnection()->beginTransaction();
            if (!is_numeric($software->vendorId)) {
                throw new Exception('Производитель не выбран');
            }
            if (false === $vendor = Vendor::findByPK($software->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            (new Software())
                ->fill([
                    'title' => $software->title,
                    'vendor' => $vendor
                ])
                ->save();
            Software::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditSoftware($software)
    {
        try {
            Software::getDbConnection()->beginTransaction();

            if (false === $updatedSoftware = Software::findByPK($software->id)) {
                throw new Exception('Неверные данные');
            }
            if (false === $vendor = Vendor::findByPK($software->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            if ($software->title != $updatedSoftware->title && false !== Software::findByColumn('title', $software->title)) {
                throw new Exception('Такое ПО существует');
            }
            $updatedSoftware
                ->fill([
                    'title' => $software->title,
                    'vendor' => $vendor
                ])
                ->save();

            Software::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelSoftware($id)
    {
        try {
            Software::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = Software::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            //Проверка использования данного объекта
            if ($item->softwareItems->count() > 0) {
                throw new Exception('Удаление невозможно. Данное ПО используется.');
            }
            $item->delete();
            $this->data->result = 'ПО "' . $item->title .  '" удалено';

            Software::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Software::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionAddVendor($vendor)
    {
        try {
            Vendor::getDbConnection()->beginTransaction();
            (new Vendor())
                ->fill([
                    'title' => $vendor->title,
                ])
                ->save();
            Vendor::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditVendor($vendor)
    {
        try {
            Vendor::getDbConnection()->beginTransaction();

            if (false === $editingObj = Vendor::findByPK($vendor->id)) {
                throw new Exception('Неверные данные');
            }
            if ($editingObj->title != $vendor->title && false !== Vendor::findByColumn('title', $vendor->title)) {
                throw new Exception('Такой производитель существует');
            }
            $editingObj
                ->fill([
                    'title' => $vendor->title
                ])
                ->save();

            Vendor::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelVendor($id)
    {
        try {
            Vendor::getDbConnection()->beginTransaction();

            //проверка правильности id
            if (false === $item = Vendor::findByPK($id)) {
                throw new Exception('Неверные данные');
            }

            //Проверка использования данного объекта
            if (
                $item->appliances->count() > 0 ||
                $item->platforms->count() > 0 ||
                $item->modules->count() > 1 ||
                $item->software->count() > 0
            ) {
                throw new Exception('Удаление невозможно. Данный производитель используется.');
            }
            $item->delete();
            $this->data->result = 'Производитель "' . $item->title .  '" удален';

            Vendor::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vendor::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDevices_old()
    {
        $timer = Timer::instance();
        $timer->fix('start action');

        if (empty($_GET)) {
            $this->data->offices = Office::findAll(['order' => 'title']);
            $this->data->regions = Region::findAll(['order' => 'title']);
        }
        if (!empty($_GET['reg'])) {
            $region = Region::findByPK((int) $_GET['reg']);
            $this->data->regions = (new Collection())->add($region);

            $this->data->offices = new Collection();
            foreach ($region->cities as $city) {
                foreach ($city->addresses as $address) {
                    $this->data->offices->add($address->office);
                }
            }
        }
        if (!empty($_GET['city'])) {
            $city = City::findByPK((int) $_GET['city']);
            $this->data->regions = (new Collection())->add($city->region);

            $this->data->offices = new Collection();
            foreach ($city->addresses as $address) {
                $this->data->offices->add($address->office);
            }
        }
        if (!empty($_GET['loc'])) {
            $office = Office::findByPK((int) $_GET['loc']);
            $this->data->offices = (new Collection())->add($office);
            $this->data->regions = $office->address->city->region;
        }
        if (!empty($_GET['cluster'])) {
            $office = Office::findByPK((int) $_GET['loc']);
            $this->data->offices = (new Collection())->add($office);
            $this->data->regions = $office->address->city->region;
        }
        if (!empty($_GET['debug'])) {
            $this->data->offices = Office::findAll(['order' => 'title']);
            $this->data->regions = Region::findAll(['order' => 'title']);
        }

        $this->data->activeLink->devices = true;
        $this->data->exportUrl = '/export/hardInvExcel';
        $timer->fix('end action');
    }

    public function actionDevices() {
        $timer = Timer::instance();
        $timer->fix('start action');

        $getParams = [
            'reg' => ['clause' => 'region_id = :region_id', 'param' => ':region_id'],
            'city' => ['clause' => 'city_id = :city_id', 'param' => ':city_id'],
            'loc' => ['clause' => 'location_id = :location_id', 'param' => ':location_id'],
            'cl' => ['clause' => 'cluster_id = :cluster_id', 'param' => ':cluster_id'],
            'type' => ['clause' => '"appType_id" = :appType_id', 'param' => ':appType_id'],
            'pl' => ['clause' => '"platform_id" = :platform_id', 'param' => ':platform_id']
        ];
        $http = new Request;
        $this->data->url = new UrlExt($http->url->toArrayRecursive());
        $where = [];
        $params = [];
        $order = GeoDevModulePort_View::sortOrder();

        if (0 == $http->get->count()) {
            $order = GeoDevModulePort_View::sortOrder();
        } else {
            $getParams = new Std($getParams);
//            var_dump($getParams);die;
            foreach ($http->get as $key => $val) {
                if (! isset($getParams->$key)) {
                    continue;
                }
                if ('order' == $key) {
                    $order = GeoDevModulePort_View::sortOrder($val);
                    continue;
                }
                $where[] = $getParams->$key->clause;
                $params[$getParams->$key->param] = $val;
            }
        }
        $where = implode(' AND ', $where);
        $query = (new QueryBuilder())
            ->select()
            ->from(GeoDevModulePort_View::getTableName())
            ->where($where)
            ->params($params)
            ->order($order);
//        var_dump($query);
        $this->data->geoDevs = GeoDevModulePort_View::findAllByQuery($query);
        $timer->fix('end action');
    }

    public function actionPortTypes()
    {
        $this->data->voicePortTypes = VPortType::findAll(['order' => 'type']);
        $this->data->dataPortTypes = DPortType::findAll(['order' => 'type']);

        $this->data->activeLink->dictionary = true;
    }

    public function actionAddPortType($portType)
    {
        try {
            VPortType::getDbConnection()->beginTransaction();
            if ('voice' == $portType->type) {
                (new VPortType())
                    ->fill([
                        'type' => $portType->title
                    ])
                    ->save();
            } elseif ('data' == $portType->type) {
                (new DPortType())
                    ->fill([
                        'type' => $portType->title
                    ])
                    ->save();
            } else {
                throw new Exception('Неизвестный тип порта');
            }

            VPortType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditPortType($portType)
    {
        try {
            VPortType::getDbConnection()->beginTransaction();
            $item = ('voice' == $portType->type) ? VPortType::findByPK($portType->id) :
                (('data' == $portType->type) ? DPortType::findByPK($portType->id) : false);

            if (false === $item) {
                throw new Exception('Неверные данные');
            }
            if ($item->type != $portType->title && false !== get_class($item)::findByColumn('type', $portType->title)) {
                throw new Exception('Такой тип существует');
            }
            $item->fill([
                'type' => $portType->title
            ]);
            $item->save();
            $this->data->result = 'Тип порта изменен';

            VPortType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }

    }

    public function actionDelPortType($portType)
    {
        try {
            VPortType::getDbConnection()->beginTransaction();
            if ('voice' == $portType->type) {
                if (false === $currentPort = VPortType::findByPK($portType->id)) {
                    throw new Exception('Порт не найден');
                }
                if ($currentPort->ports->count() > 0) {
                    throw new Exception('Удаление не возможно. Данный тип используется.');
                }
                $currentPort->delete();
            } elseif ('data' == $portType->type) {
                if (false === $currentPort = DPortType::findByPK($portType->id)) {
                    throw new Exception('Порт не найден');
                }
                if ($currentPort->ports->count() > 0) {
                    throw new Exception('Удаление не возможно. Данный тип используется.');
                }
                $currentPort->delete();

            } else {
                throw new Exception('Неизвестный тип порта');
            }

            VPortType::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            VPortType::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }


    public function actionAddAppliance($data)
    {
        try {
            Appliance::getDbConnection()->beginTransaction();

//            if (!is_numeric($data->officeId)) {
//                throw new Exception('Офис не выбран');
//            }
//            if (!is_numeric($data->vendorId)) {
//            }
//            if (!is_numeric($data->applianceTypeId)) {
//                throw new Exception('Тип оборудования не выбран');
//            }
//            if (!is_numeric($data->platformId)) {
//                throw new Exception('Платформа не выбрана');
//            }
//            if (!is_numeric($data->softwareId)) {
//                throw new Exception('ПО не выбрано');
//            }
            $office = Office::findByPK($data->officeId);
            $vendor = Vendor::findByPK($data->vendorId);
            $applianceType = ApplianceType::findByPK($data->applianceTypeId);

            $platformItem = (new PlatformItem())
                ->fill([
                    'platform' => Platform::findByPK($data->platformId),
                    'serialNumber' => $data->platformSn
                ])
                ->save();

            $softwareItem = (new SoftwareItem())
                ->fill([
                    'software' => Software::findByPK($data->softwareId),
                    'version' => $data->softwareVersion
                ])
                ->save();

            $appliance = (new Appliance())
                ->fill([
                    'location' => $office,
                    'vendor' => $vendor,
                    'platform' => $platformItem,
                    'software' => $softwareItem,
                    'type' => $applianceType,
                    'inUse' => $data->applianceInUse,
                    'comment' => $data->comment,
                    'details' => [
                                'hostname' => $data->hostname
                            ]
                        ])
                ->save();

            //если appliance сохранился без ошибок - сохраняем модули к нему
            if (!empty($data->module->id)) {
                foreach ($data->module->id as $key => $value) {
                    //если не выбран модуль - пропускаем
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $module = Module::findByPK($value);
                    $moduleItem = (new ModuleItem())
                        ->fill([
                            'appliance' => $appliance,
                            'module' => $module,
                            'serialNumber' => $data->module->sn->$key,
                            'location' => $office,
                            'comment' => $data->module->comment->$key,
                            'inUse' => $data->module->inUse->$key
                        ])
                        ->save();
                }
            }

            // если указан management IP - создать data port
            if (!empty($data->managementIp)) {
                $ip = new IpTools($data->managementIp);
                (new DataPort())->fill([
                    'ipAddress' => $ip->address,
                    'masklen' => $ip->masklen,
                    'portType' => DPortType::findByType('Ethernet'),
                    'appliance' => $appliance,
                    'vrf' => Vrf::instanceGlobalVrf(),
                    'isManagement' => true,
                ])->save();
            }

            Appliance::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditAppliance($data)
    {
        try {
            Appliance::getDbConnection()->beginTransaction();

            $currentAppliance = Appliance::findByPK($data->currentId);

            if (false === $office = Office::findByPK($data->officeId)) {
                throw new Exception('Офис не найден');
            }
            if (false === $vendor = Vendor::findByPK($data->vendorId)) {
                throw new Exception('Производитель не найден');
            }
            if (false === $applianceType = ApplianceType::findByPK($data->applianceTypeId)) {
                throw new Exception('Тип оборудования не найден');
            }
            if (false === $platform = Platform::findByPK($data->platformId)) {
                throw new Exception('Платформа не найдена');
            }
            if (false === $software = Software::findByPK($data->softwareId)) {
                throw new Exception('ПО не найдено');
            }
            //сохранение комментария к офису
            //сохраняем изменения в комменте только если не поменялся офис
            if ($currentAppliance->location->getPk() == $office->getPk() && $office->comment != $data->officeComment) {
                $office->fill(['comment' => $data->officeComment])->save();
            }
            $currentAppliance->comment = $data->comment;
            $currentAppliance->inUse = $data->applianceInUse;
            ($currentAppliance->platform)
                ->fill([
                    'platform' => $platform,
                    'serialNumber' => $data->platformSn
                ])
                ->save();

            ($currentAppliance->software)
                ->fill([
                    'software' => $software,
                    'version' => $data->softwareVersion
                ])
                ->save();

            ($currentAppliance)
                ->fill([
                    'location' => $office,
                    'vendor' => $vendor,
                    'type' => $applianceType,
                    'details' => [
                        'hostname' => $data->hostname
                    ]
                ])
                ->save();
            //если задается новый management IP
            if (!empty($data->managementIp) && !isset($data->managementIpId)) {
                $newDPort = (new DataPort())
                    ->fill([
                        'vrf' => Vrf::instanceGlobalVrf(),
                        'appliance' => $currentAppliance,
                        'ipAddress' => (new IpTools($data->managementIp))->cidrAddress,
                        'isManagement' => true,
                        'portType' => DPortType::getEmpty()
                    ]);
                $newDPort->save();

            }

            //если appliance сохранился без ошибок - сохраняем существующие модули к нему
            if (!empty($data->moduleItem->id)) {
                foreach ($data->moduleItem->id as $key => $value) {
                    //если не выбран модуль - пропускаем
                    if (!is_numeric($data->moduleItem->moduleId->$key)) {
                        continue;
                    }
                    $module = Module::findByPK($data->moduleItem->moduleId->$key);
                    $moduleItem = (ModuleItem::findByPK($data->moduleItem->id->$key))
                        ->fill([
                            'appliance' => $currentAppliance, //текущий appliance
                            'module' => $module,
                            'serialNumber' => $data->moduleItem->sn->$key,
                            'location' => $office,
                            'comment' => $data->moduleItem->comment->$key,
                            'inUse' => (boolean)$data->moduleItem->inUse->$key
                        ])
                        ->save();
                }
            }

            //сохраняем новые модули
            if (!empty($data->newModule->id)) {
                foreach ($data->newModule->id as $key => $value) {
                    //если не выбран модуль - пропускаем
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $module = Module::findByPK($value);
                    $moduleItem = (new ModuleItem())
                        ->fill([
                            'appliance' => $currentAppliance, //текущий appliance
                            'module' => $module,
                            'serialNumber' => $data->newModule->sn->$key,
                            'location' => $office,
                            'comment' => $data->newModule->comment->$key,
                            'inUse' => (boolean)$data->newModule->inUse->$key
                        ])
                        ->save();
                }
            }

            // edit data ports
            if (!empty($data->dataportItem->portId)) {
                foreach ($data->dataportItem->portId as $key => $value) {
                    //IE возвращает пустой массив
                    if (!is_numeric($value)) {
                        continue;
                    }
                    if (is_numeric($data->dataportItem->vrfId->$key)) {
                        $vrf = Vrf::findByPK($data->dataportItem->vrfId->$key);
                    } else {
                        $vrf = null;
                    }
                    //$vrf = Vrf::findByPK($data->dataportItem->vrfId->$key);
                    $currentDataPort = DataPort::findByIpVrf($data->dataportItem->ip->$key, $vrf);
                    if (isset($data->managementIpId) && !empty($data->managementIp) && $currentDataPort->getPk() == $data->managementIpId) {
                        $ip = new IpTools($data->managementIp);
                        $currentDataPort->ipAddress = $ip->address;
                        $currentDataPort->masklen = $ip->masklen;
                    }
                    $currentDataPort->fill([
                        'appliance' => $currentAppliance,
                        'vrf' => $vrf,
                        'portType' => DPortType::findByPK($data->dataportItem->portTypeId->$key),
                        'isManagement' => $data->dataportItem->isManagement->$key,
                        'macAddress' => $data->dataportItem->mac->$key,
//                        'comment' => $data->dataportItem->comment->$key,
                        'details' => [
                            'portName' => $data->dataportItem->portName->$key
                        ],
                    ])->save();
                }
            }

            // save new data ports
            if (!empty($data->newDataport->portId)) {
                foreach ($data->newDataport->portId as $key => $value) {
                    //IE возвращает пустой массив
                    if (!is_numeric($value)) {
                        continue;
                    }
                    if (!is_numeric($data->newDataport->vrfId->$key)) {
                        throw new Exception('VRF не выбран');
                    }
                    if (!is_numeric($data->newDataport->portTypeId->$key)) {
                        throw new Exception('Тип порта не выбран');
                    }

                    $vrf = Vrf::findByPK($data->newDataport->vrfId->$key);
                    $result = DataPort::findByIpVrf($data->newDataport->ip->$key, $vrf);

                    if ($result instanceof DataPort) {
                        throw new Exception('Data port: vrf=' . $vrf->name . ' ip=' . $data->newDataport->ip->$key. ' alredy exist');
                    }

                    (new DataPort())->fill([
                        'ipAddress' => $data->newDataport->ip->$key,
                        'appliance' => $currentAppliance,
                        'vrf' => $vrf,
                        'portType' => DPortType::findByPK($data->newDataport->portTypeId->$key),
                        'isManagement' => $data->newDataport->isManagement->$key,
                        'macAddress' => $data->newDataport->mac->$key,
                        'comment' => $data->newDataport->comment->$key,
                        'details' => [
                            'portName' => $data->newDataport->portName->$key
                        ],
                    ])->save();
                }
            }





            Appliance::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelAppliance()
    {
        // TO DO
    }

    public function actionPortInventory()
    {
        if (empty($_GET)) {
            $this->data->offices = Office::findAll(['order' => 'title']);
        }
        if (!empty($_GET['reg'])) {
            $this->data->offices = new Collection();
            foreach ((Region::findByPK((int) $_GET['reg']))->cities as $city) {
                foreach ($city->addresses as $address) {
                    $this->data->offices->add($address->office);
                }
            }
        }
        if (!empty($_GET['city'])) {
            $this->data->offices = new Collection();
            foreach ((City::findByPK((int) $_GET['city']))->addresses as $address) {
                $this->data->offices->add($address->office);
            }
        }
        if (!empty($_GET['loc'])) {
            $this->data->offices = (new Collection())->add(Office::findByPK((int) $_GET['loc']));
        }
    }

    public function actionAddDataPort($data)
    {
        try {
            DataPort::getDbConnection()->beginTransaction();

            if (false === $currentAppliance = Appliance::findByPK($data->id)) {
                throw new Exception('Неверные данные');
            }
            if (!is_numeric($data->portTypeId)) {
                throw new Exception('Тип порта не выбран');
            }

            if (!is_numeric($data->vrfId)) {
                throw new Exception('VRF не выбран');
            }

            (new DataPort())
                ->fill([
                    'ipAddress' => $data->ip,
                    'vrf' => Vrf::findByPK($data->vrfId),
                    'macAddress' => $data->mac,
                    'comment' => $data->comment,
                    'appliance' => $currentAppliance,
                    'portType' => DPortType::findByPK($data->portTypeId),
                    'details' => [
                        'portName' => $data->portName
                    ]
                ])
                ->save();

            DataPort::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }

    }

    public function actionEditDataPort($data)
    {
        try {
            DataPort::getDbConnection()->beginTransaction();

            if (false === $currentAppliance = Appliance::findByPK($data->applianceId)) {
                throw new Exception('Неверные данные');
            }
            if (false === $currentDataPort = DataPort::findByPK($data->portId)) {
                throw new Exception('Неверные данные');
            }

            if (!is_numeric($data->vrfId)) {
                throw new Exception('VRF не выбран');
            }
            if ($currentDataPort->ipAddress != $data->ip) {
                $currentDataPort->ipAddress = $data->ip;
            }
            $currentDataPort
                ->fill([
                    'vrf' => Vrf::findByPK($data->vrfId),
                    'macAddress' => $data->mac,
                    'comment' => $data->comment,
                    'appliance' => $currentAppliance, //нужен только для валидатора
                    'portType' => DPortType::findByPK($data->portTypeId),
                    'details' => [
                        'portName' => $data->portName
                    ],
                    'isManagement' => $data->isManagement
                ]);
            $currentDataPort->save();

            DataPort::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }

    }

    public function actionDelDataPort($portId)
    {
        try {
            DataPort::getDbConnection()->beginTransaction();

            if (false === $currentDataPort = DataPort::findByPK($portId)) {
                throw new Exception('Неверные данные');
            }
            $currentDataPort->delete();

            $this->data->result = 'Порт с IP адресом ' . $currentDataPort->ipAddress . ' удален';

            DataPort::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }

    }

    public function actionVrf()
    {
        $this->data->vrfs = Vrf::findAll(['name' => 'asc']);
        $this->data->gvrf = Vrf::instanceGlobalVrf();
        $this->data->activeLink->ipPlanning = true;
    }

    public function actionAddVrf($vrf)
    {
        try {
            Vrf::getDbConnection()->beginTransaction();
            (new Vrf())
                ->fill($vrf)
                ->save();

            Vrf::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionEditVrf($vrf)
    {
        try {
            Vrf::getDbConnection()->beginTransaction();
            if (false === $currentVrf = Vrf::findByPK($vrf->id)) {
                throw new Exception('Неверные данные');
            }
            unset($vrf->id);
            $currentVrf
                ->fill($vrf)
                ->save();

            Vrf::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelVrf($id)
    {
        try {
            Vrf::getDbConnection()->beginTransaction();
            if (false === $currentVrf = Vrf::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            $currentVrf->delete();

            Vrf::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionNetworksTab()
    {
        $this->data->networks = Network::findAll(['vrf' => 'asc', 'address' => 'asc']);
    }

    public function actionAddNetwork($network)
    {
        try {
            Network::getDbConnection()->beginTransaction();
            (new Network())
                ->fill([
                    'address' => $network->address,
                    'comment' => $network->comment,
                    'vrf' => Vrf::findByPK($network->vrfId)
                ])
                ->save();

            Network::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionDelNetwork($id)
    {
        try {
            Network::getDbConnection()->beginTransaction();
            if (false === $currentNetwork = Network::findByPK($id)) {
                throw new Exception('Неверные данные');
            }
            $currentNetwork->delete();

            Network::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        } catch (Exception $e) {
            Vrf::getDbConnection()->rollbackTransaction();
            $this->data->errors = (new MultiException())->add($e);
        }
    }

    public function actionNetworksTree()
    {
        $allVrf = Vrf::findAll(['name' => 'asc']);
        $vrfs = new Collection();
        foreach ($allVrf as $vrf) {
            $vrf->rootNetworks = Network::findAllRootsByVrf($vrf, ['address' => 'asc']);
            $vrfs->append($vrf);
        }
        $this->data->vrfs = $vrfs;
    }
}