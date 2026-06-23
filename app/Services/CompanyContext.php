<?php

namespace App\Services;

use App\Models\Company;

class CompanyContext
{
    protected ?Company $company = null;

    protected bool $allCompanies = false;

    protected bool $noCompanyAccess = false;

    public function set(?Company $company): self
    {
        $this->company = $company;
        $this->allCompanies = false;
        $this->noCompanyAccess = false;

        return $this;
    }

    public function all(): self
    {
        $this->company = null;
        $this->allCompanies = true;
        $this->noCompanyAccess = false;

        return $this;
    }

    public function none(): self
    {
        $this->company = null;
        $this->allCompanies = false;
        $this->noCompanyAccess = true;

        return $this;
    }

    public function clear(): self
    {
        $this->company = null;
        $this->allCompanies = false;
        $this->noCompanyAccess = false;

        return $this;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function hasCompany(): bool
    {
        return $this->company !== null;
    }

    public function isAllCompanies(): bool
    {
        return $this->allCompanies;
    }

    public function deniesCompanyAccess(): bool
    {
        return $this->noCompanyAccess;
    }
}
