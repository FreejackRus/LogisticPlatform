export enum FreightRole {
    Admin = 'admin',
    Shipper = 'shipper',
    Carrier = 'carrier',
    Dispatcher = 'dispatcher',
}

export enum LoadStatus {
    Draft = 'draft',
    Active = 'active',
    InProgress = 'in_progress',
    Completed = 'completed',
    Cancelled = 'cancelled',
    Archived = 'archived',
}

export enum BidStatus {
    Pending = 'pending',
    Accepted = 'accepted',
    Rejected = 'rejected',
    Cancelled = 'cancelled',
}
