<?php
declare(strict_types=1);
namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_settings')]
class SiteSetting
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 100)]
    private string $settingKey;

    #[ORM\Column(type: 'text')]
    private string $value;

    #[ORM\Column(type: 'string', length: 50)]
    private string $category;

    public function __construct(string $settingKey, string $value, string $category = 'general')
    {
        $this->settingKey = $settingKey;
        $this->value = $value;
        $this->category = $category;
    }

    public function getSettingKey(): string { return $this->settingKey; }
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): void { $this->value = $value; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): void { $this->category = $category; }
}
