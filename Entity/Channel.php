<?php

namespace Oro\Bundle\IntegrationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\DataGridBundle\Common\Object as ConfigObject;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\DataAuditBundle\Metadata\Annotation as Oro;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;

/**
 * @ORM\Table(name="oro_integration_channel")
 * @ORM\Entity(repositoryClass="Oro\Bundle\IntegrationBundle\Entity\Repository\ChannelRepository")
 * @Config(
 *  routeName="oro_integration_index",
 *  defaultValues={
 *      "security"={
 *          "type"="ACL",
 *          "group_name"=""
 *      }
 *  }
 * )
 * @Oro\Loggable()
 */
class Channel
{
    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(type="smallint", name="id")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Oro\Versioned()
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     * @Oro\Versioned()
     */
    protected $type;

    /**
     * @var Transport
     *
     * @ORM\OneToOne(targetEntity="Oro\Bundle\IntegrationBundle\Entity\Transport",
     *     cascade={"all"}, orphanRemoval=true
     * )
     */
    protected $transport;

    /**
     * @var []
     * @ORM\Column(name="connectors", type="array")
     * @Oro\Versioned()
     */
    protected $connectors;

    /**
     * @var ConfigObject
     *
     * @ORM\Column(name="synchronization_settings", type="object", nullable=false)
     */
    protected $synchronizationSettings;

    /**
     * @var boolean
     *
     * @ORM\Column(name="disabled", type="boolean", nullable=true)
     * @Oro\Versioned()
     */
    protected $disabled;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="default_user_owner_id", referencedColumnName="id", onDelete="SET NULL")
     * @Oro\Versioned()
     */
    protected $defaultUserOwner;

    /**
     * @var Organization
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OrganizationBundle\Entity\Organization")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", onDelete="SET NULL")
     * @Oro\Versioned()
     */
    protected $organization;

    /**
     * @var Status[]|ArrayCollection
     *
     * Cascade persisting is not used due to lots of detach/merge
     * @ORM\OneToMany(targetEntity="Oro\Bundle\IntegrationBundle\Entity\Status",
     *     cascade={"merge"}, orphanRemoval=true, mappedBy="channel"
     * )
     * @ORM\OrderBy({"date" = "DESC"})
     */
    protected $statuses;

    public function __construct()
    {
        $this->statuses                = new ArrayCollection();
        $this->synchronizationSettings = ConfigObject::create([]);
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Transport $transport
     *
     * @return $this
     */
    public function setTransport(Transport $transport)
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * @return Transport
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @return $this
     */
    public function clearTransport()
    {
        $this->transport = null;

        return $this;
    }

    /**
     * @param [] $connectors
     *
     * @return $this
     */
    public function setConnectors($connectors)
    {
        $this->connectors = $connectors;

        return $this;
    }

    /**
     * @return []
     */
    public function getConnectors()
    {
        return $this->connectors;
    }

    /**
     * @param ConfigObject $synchronizationSettings
     */
    public function setSynchronizationSettings($synchronizationSettings)
    {
        $this->synchronizationSettings = $synchronizationSettings;
    }

    /**
     * @return ConfigObject
     */
    public function getSynchronizationSettings()
    {
        return clone $this->synchronizationSettings;
    }

    /**
     * @return ConfigObject
     */
    public function getSynchronizationSettingsReference()
    {
        return $this->synchronizationSettings;
    }

    /**
     * @param Status $status
     *
     * @return $this
     */
    public function addStatus(Status $status)
    {
        if (!$this->statuses->contains($status)) {
            $status->setChannel($this);
            $this->statuses->add($status);
        }

        return $this;
    }

    /**
     * @return ArrayCollection|Status[]
     */
    public function getStatuses()
    {
        return $this->statuses;
    }

    /**
     * @param string  $connector
     * @param int|int $codeFilter
     *
     * @return ArrayCollection
     */
    public function getStatusesForConnector($connector, $codeFilter = null)
    {
        return $this->statuses->filter(
            function (Status $status) use ($connector, $codeFilter) {
                $connectorFilter = $status->getConnector() === $connector;
                $codeFilter      = $codeFilter === null ? true : $status->getCode() == $codeFilter;

                return $connectorFilter && $codeFilter;
            }
        );
    }

    /**
     * @param User $owner
     *
     * @return $this
     */
    public function setDefaultUserOwner(User $owner = null)
    {
        $this->defaultUserOwner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getDefaultUserOwner()
    {
        return $this->defaultUserOwner;
    }

    /**
     * @param Organization $organization
     */
    public function setOrganization($organization)
    {
        $this->organization = $organization;
    }

    /**
     * @return Organization
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * @param boolean $disabled
     */
    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;
    }

    /**
     * @return boolean
     */
    public function getDisabled()
    {
        return $this->disabled;
    }
}
