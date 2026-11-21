<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\ServiceInfo;

/**
 * Service Info for a Canada Post platform.
 *
 * This interface is not intended to be implemented outside of this library. New
 * methods may be added as more endpoints are supported.
 *
 * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/serviceinfo/default.jsf
 */
interface ServiceInfoClientInterface
{
    /**
     * Get messages about scheduled outages to web services.
     *
     * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/serviceinfo/getserviceinfo.jsf
     *
     * @return list<InfoMessage>
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     */
    public function getServiceInfo(): array;
}
