<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Hybrid-KVM infrastructure + the two new product families. Create-only (never overwrites admin edits).
 *
 * NOTHING here is verified or priced: suppliers start as manual with an UNVERIFIED API status and no verified
 * capabilities; new plans are draft slots with no price/resources and cannot be purchased.
 */
class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['DesiVPS', 'desivps', 'vps_supplier', true],
            ['EricHost', 'erichost', 'vps_supplier', true],
            ['Contabo Core', 'contabo-core', 'vps_supplier', true],
            ['Contabo Performance Plus', 'contabo-performance-plus', 'vps_supplier', true],
            ['Server Broker', 'server-broker', 'broker', true],
            ['OVHcloud dedicated servers (future)', 'ovhcloud-dedicated', 'dedicated_provider', false],
            ['Dedicated Proxmox KVM cluster (future)', 'proxmox-internal', 'proxmox_internal', false],
        ] as [$name, $slug, $kind, $active]) {
            Provider::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'kind' => $kind, 'is_active' => $active, 'availability' => 'unknown',
                'provisioning_method' => $kind === 'proxmox_internal' ? 'proxmox' : 'manual', 'api_status' => 'unverified',
                'verified_capabilities' => [],
                'notes' => $active ? 'Initial supplier: on-demand purchase and manual assignment. API capability NOT verified.' : 'Future infrastructure — disabled until hardware, networking, storage and security testing are approved.',
            ]);
        }

        foreach ([
            ['claude-code', 'Claude Code Hosting', 'A managed VPS prepared for running Claude Code with your own eligible Claude subscription or API credentials.', 6, 'claude-code', 'managed'],
            ['docker-vps', 'Docker VPS Hosting', 'An unmanaged developer VPS with Docker Engine and Compose for your own containers.', 7, 'docker-vps', 'unmanaged'],
        ] as [$slug, $name, $description, $order, $planSlug, $access]) {
            $category = ProductCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'sort_order' => $order, 'is_active' => true]);

            // Proposed placeholder names (owner may rename in Admin → Plans). Slugs stay -a/-b/-c.
            $drafts = [
                'a' => ['Starter', 'Draft entry plan for individual projects — pricing and resources to be confirmed.'],
                'b' => ['Growth', 'Draft mid plan for regular workloads — pricing and resources to be confirmed.'],
                'c' => ['Business', 'Draft upper plan for teams — pricing and resources to be confirmed.'],
            ];
            foreach ($drafts as $i => [$planName, $tagline]) {
                Plan::firstOrCreate(['slug' => $planSlug.'-'.$i], [
                    'product_category_id' => $category->id, 'name' => $planName,
                    'tagline' => $tagline,
                    'highlight' => 'Pricing and resources to be confirmed', 'features' => [], 'is_active' => true,
                    'is_purchasable' => false, 'price_status' => 'pending', 'sort_order' => array_search($i, ['a', 'b', 'c']) + 1,
                    'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => $access,
                ]);
            }
        }
    }
}
