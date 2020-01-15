<?php

namespace App\Controllers;

use App\Controllers\Dtos\DomainDto;
use App\Controllers\Params\GetDomainPricesParams;
use App\Domains\Repositories\DomainRepository;
use App\Domains\Repositories\TldRepository;
use App\Utils\Domains;
use App\Utils\ObjectArrays;
use Yii;
use yii\filters\ContentNegotiator;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

class DomainsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        return $behaviors;
    }

    /**
     * Get domain with prices
     *
     * @return DomainDto[]
     * @throws BadRequestHttpException
     */
    public function actionCheck(): array
    {
        $params = new GetDomainPricesParams();
        if (!$params->load(Yii::$app->request->get(), '') || !$params->validate()) {
            throw new BadRequestHttpException();
        }


        // get container EM
        $em = Yii::$container->get('Doctrine\ORM\EntityManager');

        // todo найти список tld из таблицы
        $tr = new TldRepository($em);
        $tlds = $tr->findAll();

        // todo создать список доменов
        $domains = Domains::fromNameAndTlds($params['search'], ObjectArrays::createFieldArray($tlds, 'tld'));

        // todo проверить домены на корректность имени
        $domains = array_filter($domains, ["App\Utils\Domains", "valid"]);

        // todo проверить наличие домена в таблице domain
        $dr = new DomainRepository($em);
        $domains = array_filter($domains, function($item) use ($dr) {
            if($dr->findOneByDomain($item) !== null) {
                return false;
            }
            return true;
        });

        // todo создать список dto с ценами для списка доменов
        /** @var DomainDto[] $dtos */
        $dtos = [];
        foreach($domains as $domain) {
            preg_match('/\.(\w+)$/', $domain, $matches);
//            VarDumper::dump($tld, 100, true);exit();
            $tldObject = ObjectArrays::filterEqualOne($tlds, 'tld', $matches[1]);
            $dtos[] = new DomainDto($matches[1], $domain, $tldObject->price, true);
        };

        return $dtos;
    }
}
